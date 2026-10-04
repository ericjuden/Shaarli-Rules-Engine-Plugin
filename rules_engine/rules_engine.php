<?php

declare(strict_types=1);

/**
 * Rules Engine Plugin.
 *
 * Automatically suggests tags for bookmarks based on configurable URL pattern rules.
 * Hooks into the render_editlink event to pre-populate the tags field in the bookmark form.
 * Users can see, modify, or remove the suggested tags before saving.
 */

use Shaarli\Config\ConfigManager;

/**
 * Initialization function.
 * It will be called when the plugin is loaded.
 * This function validates rules configuration and returns any errors.
 *
 * @param ConfigManager $conf ConfigManager instance.
 *
 * @return array List of errors (optional).
 */
function rules_engine_init(ConfigManager $conf): array
{
    $errors = [];
    $rulesConfig = $conf->get('plugins.RULES_ENGINE_RULES', '');

    if (empty($rulesConfig)) {
        return $errors;
    }

    // Split on semicolons (primary) or newlines (fallback for multiline input)
    $lines = preg_split('/[;\n]+/', $rulesConfig);
    $ruleNumber = 0;

    foreach ($lines as $line) {
        $ruleNumber++;
        $trimmedLine = trim($line);

        // Skip empty lines and whitespace-only lines
        if (empty($trimmedLine)) {
            continue;
        }

        // Check for pipe delimiter
        if (strpos($trimmedLine, '|') === false) {
            $errors[] = 'Rules Engine: Malformed rule #' . $ruleNumber . ' - missing pipe delimiter: "' . $trimmedLine . '"';
            continue;
        }

        $parts = explode('|', $trimmedLine, 2);
        $pattern = trim($parts[0]);
        $tags = trim($parts[1] ?? '');

        // Validate pattern and tags are non-empty
        if (empty($pattern)) {
            $errors[] = 'Rules Engine: Malformed rule #' . $ruleNumber . ' - empty pattern';
        }
        if (empty($tags)) {
            $errors[] = 'Rules Engine: Malformed rule #' . $ruleNumber . ' - empty tags';
        }
    }

    return $errors;
}

/**
 * Parse rules configuration into array of [pattern, tags] tuples.
 * Rules are separated by semicolons (;) or newlines.
 *
 * @param string $rulesConfig The configuration string with semicolon-separated rules.
 *
 * @return array Array of ['pattern' => string, 'tags' => array] tuples.
 */
function rules_engine_parse_rules(string $rulesConfig): array
{
    $rules = [];

    if (empty($rulesConfig)) {
        return $rules;
    }

    // Split on semicolons (primary) or newlines (fallback for multiline input)
    $rules_raw = preg_split('/[;\n]+/', $rulesConfig);

    foreach ($rules_raw as $rule_string) {
        $trimmedRule = trim($rule_string);

        // Skip empty lines and whitespace-only lines
        if (empty($trimmedRule)) {
            continue;
        }

        // Check for pipe delimiter - skip malformed rules
        if (strpos($trimmedRule, '|') === false) {
            continue;
        }

        $parts = explode('|', $trimmedRule, 2);
        $pattern = trim($parts[0]);
        $tagsString = trim($parts[1] ?? '');

        // Skip if pattern or tags are empty
        if (empty($pattern) || empty($tagsString)) {
            continue;
        }

        // Parse tags (comma-separated)
        $tags = array_map('trim', explode(',', $tagsString));
        $tags = array_filter($tags, function ($tag) {
            return !empty($tag);
        });

        if (!empty($tags)) {
            $rules[] = [
                'pattern' => $pattern,
                'tags' => array_values($tags)
            ];
        }
    }

    return $rules;
}

/**
 * Convert a glob pattern to a regex pattern.
 * Converts * to .* for regex matching.
 *
 * @param string $pattern The glob-style pattern.
 *
 * @return string The regex pattern.
 */
function rules_engine_glob_to_regex(string $pattern): string
{
    // Escape all regex special characters except *
    $escaped = preg_quote($pattern, '/');
    // Replace escaped \* with .* for wildcard matching
    $regex = str_replace('\*', '.*', $escaped);
    return '/^' . $regex . '$/';
}

/**
 * Match a URL against a glob-style pattern.
 *
 * @param string $pattern The glob-style pattern.
 * @param string $url The URL to match.
 *
 * @return bool True if the URL matches the pattern.
 */
function rules_engine_match_pattern(string $pattern, string $url): bool
{
    try {
        $regex = rules_engine_glob_to_regex($pattern);
        $result = preg_match($regex, $url);
        return $result === 1;
    } catch (\Exception $e) {
        // On pattern matching failure, return false
        return false;
    }
}

/**
 * Find all rules that match a given URL.
 *
 * @param array $rules Array of rule definitions.
 * @param string $url The URL to match against.
 *
 * @return array Array of matching rules.
 */
function rules_engine_find_matching_rules(array $rules, string $url): array
{
    $matchingRules = [];

    foreach ($rules as $rule) {
        if (rules_engine_match_pattern($rule['pattern'], $url)) {
            $matchingRules[] = $rule;
        }
    }

    return $matchingRules;
}

/**
 * Extract all tags from matching rules.
 *
 * @param array $matchingRules Array of matching rule definitions.
 *
 * @return array Array of tags from all matching rules.
 */
function rules_engine_extract_tags(array $matchingRules): array
{
    $tags = [];

    foreach ($matchingRules as $rule) {
        $tags = array_merge($tags, $rule['tags']);
    }

    return $tags;
}

/**
 * Merge existing tags with auto-generated tags, removing duplicates (case-insensitive).
 *
 * @param array $existingTags Array of existing tags.
 * @param array $autoTags Array of auto-generated tags.
 *
 * @return array Merged array of unique tags.
 */
function rules_engine_merge_tags(array $existingTags, array $autoTags): array
{
    $mergedTags = array_merge($existingTags, $autoTags);

    // Case-insensitive duplicate removal
    $seen = [];
    $uniqueTags = [];

    foreach ($mergedTags as $tag) {
        $lowerTag = strtolower($tag);
        if (!isset($seen[$lowerTag])) {
            $seen[$lowerTag] = true;
            $uniqueTags[] = $tag;
        }
    }

    return $uniqueTags;
}

/**
 * Hook render_editlink.
 *
 * Triggered when the bookmark edit form is displayed.
 * Pre-populates the tags field with auto-suggested tags based on URL pattern matching rules.
 * Users can see, modify, or remove these tags before saving.
 *
 * @param array $data Contains the form data including link[url] and link[tags].
 * @param ConfigManager $conf ConfigManager instance.
 *
 * @return array Altered $data with pre-populated tags.
 */
function hook_rules_engine_render_editlink(array $data, ConfigManager $conf): array
{
    // Read rules configuration from ConfigManager
    $rulesConfig = $conf->get('plugins.RULES_ENGINE_RULES', '');

    if (empty($rulesConfig)) {
        return $data;
    }

    // Parse rules
    $rules = rules_engine_parse_rules($rulesConfig);

    if (empty($rules)) {
        return $data;
    }

    // Get the bookmark URL from form data
    $url = $data['link']['url'] ?? '';

    if (empty($url)) {
        return $data;
    }

    // Find matching rules
    $matchingRules = rules_engine_find_matching_rules($rules, $url);

    if (empty($matchingRules)) {
        return $data;
    }

    // Extract auto-tags from matching rules
    $autoTags = rules_engine_extract_tags($matchingRules);

    // Get existing tags from form (space-separated string to array)
    $existingTagsString = $data['link']['tags'] ?? '';
    $existingTags = [];
    if (!empty($existingTagsString)) {
        $existingTags = preg_split('/\s+/', trim($existingTagsString));
        $existingTags = array_filter($existingTags, function ($tag) {
            return !empty($tag);
        });
    }

    // Merge tags (case-insensitive duplicate removal)
    $mergedTags = rules_engine_merge_tags($existingTags, $autoTags);

    // Update tags in form data (convert back to space-separated string)
    // Add trailing space to match Shaarli's tag formatting convention
    $data['link']['tags'] = implode(' ', $mergedTags);
    if (!empty($data['link']['tags'])) {
        $data['link']['tags'] .= ' ';
    }

    return $data;
}

/**
 * This function is never called, but contains translation calls for GNU gettext extraction.
 */
function rules_engine_dummy_translation(): void
{
    // meta
    t('Automatically suggests tags for bookmarks based on configurable URL pattern rules.');
    t('Rules separated by semicolons (;). Format: pattern|tag1,tag2; Example: github.com/*|github,code; *.youtube.com/*|video');
}

