# Rules Engine Plugin

Automatically suggests tags for bookmarks based on configurable URL pattern rules. When you add or edit a bookmark, matching tags are **pre-populated in the tags input field** so you can see, modify, or remove them before saving.

## Features

- **Visible Auto-Tags**: Suggested tags appear in the tags input field, not hidden
- **User Control**: Review, modify, or remove suggested tags before saving
- **Glob-Style Pattern Matching**: Use simple wildcard patterns (e.g., `github.com/*`) that are easy to configure
- **Multiple Tags Per Rule**: Define multiple comma-separated tags for each pattern
- **Tag Merging**: Auto-tags are merged with any existing tags without duplicates
- **All Rules Apply**: Tags from ALL matching rules are applied (not just first match)

## How It Works

1. **You open the "Add a new link" form** with a URL (e.g., `github.com/user/repo`)
2. **Plugin matches the URL** against your configured rules
3. **Matching tags appear in the tags field** - you can see them immediately!
4. **You have full control:**
    - Keep the suggested tags as-is ✓
    - Remove unwanted tags ✓
    - Add additional tags ✓
    - Edit any tag ✓
5. **You save the bookmark** with exactly the tags you want

This is much better than hidden auto-tagging because you always know exactly what tags will be saved.

## Configuration

After enabling the plugin in Shaarli's Plugin Administration, configure your rules in the `RULES_ENGINE_RULES` parameter.

### Rule Format

Rules are **separated by semicolons (`;`)** with each rule in this format:

```
pattern|tag1,tag2,tag3
```

- **pattern**: A glob-style pattern to match against bookmark URLs
- **tags**: Comma-separated list of tags to suggest when the pattern matches
- **semicolon**: Separates multiple rules

### Configuration Example

Enter this in the RULES_ENGINE_RULES field:

```
github.com/*|github,repository; stackoverflow.com/*|stackoverflow,qa; *.youtube.com/watch*|video,youtube; developer.mozilla.org/*|mdn,documentation
```

### Multiple Rules Breakdown

| Rule | Pattern                   | Tags               |
| ---- | ------------------------- | ------------------ |
| 1    | `github.com/*`            | github, repository |
| 2    | `stackoverflow.com/*`     | stackoverflow, qa  |
| 3    | `*.youtube.com/watch*`    | video, youtube     |
| 4    | `developer.mozilla.org/*` | mdn, documentation |

## Glob-Style Pattern Matching

The plugin uses simple glob-style wildcards where `*` matches zero or more characters.

### Pattern Examples

| Pattern                 | Matches                           | Does Not Match                 |
| ----------------------- | --------------------------------- | ------------------------------ |
| `github.com/*`          | `github.com/user/repo`            | `gitlab.com/project`           |
| `*.github.com/*`        | `gist.github.com/user/abc`        | `github.com/user`              |
| `github.com/*/issues/*` | `github.com/user/repo/issues/123` | `github.com/user/repo/pulls/1` |
| `*.youtube.com/watch*`  | `www.youtube.com/watch?v=xyz`     | `youtube.com/playlist`         |
| `example.com`           | `example.com`                     | `example.com/page`             |

### Important Notes

- **Case-Sensitive**: Pattern matching is case-sensitive by default
    - `github.com/*` will **NOT** match `GitHub.com/user/repo`
    - Use lowercase patterns to match lowercase URLs (the most common case)
- **Wildcards**: The `*` character matches any sequence of characters (including none)
- **No Regex**: This plugin uses simple glob patterns, not regular expressions

## Layered Tagging Example

With this configuration:

```
github.com/*|github; github.com/*/issues/*|issue-tracker; github.com/shaarli/*|shaarli
```

A bookmark for `github.com/shaarli/Shaarli/issues/123` would show these tags in the form:

- `github` (from rule 1)
- `issue-tracker` (from rule 2)
- `shaarli` (from rule 3)

You can then remove any you don't want before saving!

## Common Use Cases

### Development Resources

```
github.com/*|github,code; gitlab.com/*|gitlab,code; stackoverflow.com/*|stackoverflow,qa,programming; developer.mozilla.org/*|mdn,documentation,webdev
```

### Media & Entertainment

```
*.youtube.com/watch*|youtube,video; *.vimeo.com/*|vimeo,video; *.spotify.com/*|spotify,music; *.soundcloud.com/*|soundcloud,music
```

### News & Reading

```
*.medium.com/*|medium,article; *.substack.com/*|substack,newsletter; news.ycombinator.com/*|hackernews,tech
```

### Shopping & Commerce

```
*.amazon.com/*|amazon,shopping; *.ebay.com/*|ebay,shopping
```

## User Experience

### What You'll See

When you add a bookmark for `github.com/user/awesome-project`:

1. Open the "Add a new link" form
2. The URL field shows: `github.com/user/awesome-project`
3. The **tags field automatically shows**: `github repository ` (with space to add more)
4. You can:
    - Type additional tags after the suggested ones
    - Delete any suggested tags you don't want
    - Leave it as-is if the suggestions are perfect

### Why This Approach?

- **Transparency**: You see exactly what tags will be saved
- **Control**: You can always override the suggestions
- **Trust**: No hidden behavior - what you see is what you get
- **Flexibility**: Easy to add context-specific tags alongside auto-suggestions

## Troubleshooting

### Rules Not Appearing in Tags Field

1. **Check pattern case**: Patterns are case-sensitive. Use lowercase for most URLs.
2. **Verify wildcard placement**: `*.github.com/*` requires a subdomain, while `github.com/*` matches the main domain.
3. **Check semicolons**: Make sure rules are separated by `;`
4. **Check the pipe**: Ensure `|` separates pattern from tags
5. **Reload the form**: If you just changed rules, reload the add/edit form.

### Validation Errors

The plugin validates rules on initialization. Common errors:

| Error                    | Cause                     | Fix                               |
| ------------------------ | ------------------------- | --------------------------------- |
| `missing pipe delimiter` | Rule doesn't contain `\|` | Add `\|` between pattern and tags |
| `empty pattern`          | Nothing before the `\|`   | Add a URL pattern before `\|`     |
| `empty tags`             | Nothing after the `\|`    | Add at least one tag after `\|`   |

### Malformed Rules

If some rules are malformed, the plugin will:

- Skip the malformed rules
- Continue suggesting tags from valid rules
- Show validation errors in the plugin administration

### Tags Not Merging Correctly

- Duplicate detection is **case-insensitive**: `GitHub` and `github` are considered the same
- The first occurrence is kept: if the form already has `GitHub`, and a rule suggests `github`, you'll have `GitHub`

## Technical Details

- **Hook**: Uses `render_editlink` hook (fires when bookmark form is displayed)
- **Configuration**: Stored in Shaarli's config via `plugins.RULES_ENGINE_RULES`
- **Rule Delimiter**: Semicolon (`;`) separates rules; newlines also supported
- **Performance**: Designed for small rule sets (~20 rules is reasonable)
- **No Database**: Pure configuration-based, no additional storage needed
- **Visible Tags**: Tags appear in `$data['link']['tags']` which populates the form input

## Installation

1. Copy the `rules_engine` folder to your Shaarli `plugins/` directory
2. Enable the plugin in Shaarli Admin → Plugin Administration
3. Configure your rules in the plugin settings (semicolon-separated)
4. Add a new bookmark to test - you should see suggested tags in the tags field!

## Rollback

To disable the plugin:

1. Go to Plugin Administration
2. Disable the Rules Engine plugin
3. Existing bookmarks keep their tags (they were saved with user approval)
