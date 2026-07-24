# AI Translate Placement for Moodle

A powerful Moodle plugin that enables AI-powered content translation on course pages. This plugin leverages Moodle's AI framework to provide instant, high-quality translations of course content into multiple languages.

## Features

- **One-Click Translation** — Translate full page content or selected text with a single click
- **Multi-Language Support** — Support for 20+ languages, with admin-configurable language lists
- **Smart Selection Detection** — Automatically detects user text selections and offers targeted translation
- **User Preferences** — Remembers user's language preference across sessions
- **Accessible UI** — WCAG-compliant drawer interface with keyboard navigation and ARIA support
- **Policy Framework Integration** — Respects Moodle's AI policy requirements with automatic consent flow
- **Mobile-Friendly** — Responsive design that works on desktop, tablet, and mobile devices
- **Performance-Optimized** — Client-side caching prevents redundant translation requests
- **No Data Storage** — Plugin implements Moodle's privacy standards—translations are not persisted

## Requirements

- **Moodle 5.0+** (released December 2025 or later)
- **PHP 8.2+**
- **Moodle AI Framework** (`core_ai` component) configured with at least one AI provider
  - Tested with OpenAI provider
  - Should work with any provider implementing `core_ai\aiactions\generate_text`
- **JavaScript enabled** in browser

## Installation

### From Moodle Marketplace

1. Log in as an administrator
2. Navigate to **Site administration → Plugins → Install plugins**
3. Search for "Translate placement" or "aiplacement_translate"
4. Click **Install**
5. Follow the prompts to complete installation

### Manual Installation

1. Clone or download the plugin to your Moodle installation:
   ```bash
   cd /path/to/moodle
   git clone https://github.com/sumitnegi933/moodle-aiplacement_translate.git public/ai/placement/translate
   ```

2. Log in as an administrator and navigate to **Site administration → Notifications**

3. The plugin will be automatically installed, or click the upgrade button if prompted

## Configuration

### Enable the Plugin

1. Navigate to **Site administration → Plugins → AI Placements**
2. Ensure **Translate placement** is enabled

### Configure Languages

1. Go to **Site administration → Plugins → AI Placements → Translate**
2. Edit the **Available languages** setting
3. Enter one language name per line (e.g., `Spanish`, `French`, `German`)
4. Leave blank to use the default language list (20 languages)

**Default Languages:**
English, Spanish, French, German, Italian, Portuguese, Russian, Chinese (Simplified), Chinese (Traditional), Japanese, Korean, Arabic, Dutch, Polish, Swedish, Turkish, Hindi, Bengali, Vietnamese, Thai

### Set Up AI Provider

Ensure your Moodle instance has a working AI provider configured:

1. Navigate to **Site administration → AI → Manage providers**
2. Configure your AI provider (e.g., OpenAI)
3. Enable the `Generate text` action for your provider
4. Set API keys and configure rate limits as needed

### Manage Capabilities

By default, all authenticated users (manager, teacher, student) can translate content. To restrict access:

1. Navigate to **Site administration → Users → Permissions → Define roles**
2. Select the role you want to modify
3. Find the capability `aiplacement/translate:translate_text`
4. Set the permission to **Not set** or **Prevent** as needed

## Usage

### For Students/Users

**Translate Full Page:**
1. Click the **Translate** button (sparkles icon) in the course page controls
2. Select your preferred language from the modal
3. Click **Save & Translate**
4. The translated content appears in the drawer on the right

**Translate Selected Text:**
1. Highlight text on the page with your mouse
2. Click the **Translate** button
3. You'll see a preview of your selection
4. Click the drawer's **Translate** button to confirm
5. Select your language
6. Click **Save & Translate**

**Regenerate Translation:**
- Click the **Retranslate** button in the drawer to regenerate a new translation
- Change the language dropdown to translate to a different language

### For Administrators

**Monitor Usage:**
- Check your AI provider's dashboard for translation request logs
- Monitor for unusual usage patterns or potential abuse

**Customize Languages:**
- Go to **Site administration → Plugins → AI Placements → Translate**
- Add/remove languages to match your course needs
- Separate each language name on a new line

**Troubleshoot Issues:**
- Check that your AI provider is configured and working
- Verify user has the `aiplacement/translate:translate_text` capability
- Check browser console for JavaScript errors (F12 key)
- Verify Moodle's caches are cleared (Site administration → Development → Purge caches)

## Browser Compatibility

- Chrome 90+
- Firefox 88+
- Safari 14+
- Edge 90+
- Mobile browsers (iOS Safari, Chrome Mobile)

## Accessibility

This plugin meets WCAG 2.1 Level AA standards:

- Full keyboard navigation support (Tab, Enter, Escape)
- ARIA labels and live regions for screen readers
- Focus management and visible focus indicators
- High contrast drawer for readability
- Responsive text sizing

## Privacy & Data

- **No personal data storage** — The plugin does not save translations, user selections, or translation history
- **Language preference** — Only the user's preferred language is stored in user preferences (not shared)
- **AI provider usage** — Your AI provider (e.g., OpenAI) may log requests per their privacy policy; review your provider's terms
- **GDPR compliant** — No personal data is retained by the plugin itself

For the full privacy declaration, see the [Privacy Policy](./classes/privacy/provider.php).

## Testing

### Running Unit Tests

```bash
cd /path/to/moodle
php admin/tool/phpunit/cli/init.php
php admin/tool/phpunit/cli/util.php --buildconfig
vendor/bin/phpunit --configuration phpunit.xml --testsuite=aiplacement_translate_testsuite
```

### Running Behat Tests (if available)

```bash
php admin/tool/behat/cli/init.php
vendor/bin/behat --config=/path/to/behat.yml --tags=@aiplacement_translate
```

## Troubleshooting

### Translate button not appearing

- Check that the plugin is enabled in **Site administration → Plugins → AI Placements**
- Verify you're on a course module page (activity/resource context)
- Clear your browser cache and Moodle caches
- Check browser console for JavaScript errors (F12 → Console tab)

### "Translation service is unavailable" error

- Verify your AI provider is configured and has a valid API key
- Check that the `Generate text` action is enabled for your provider
- Review your API quota/rate limits
- Check your internet connection

### Language modal not appearing

- Ensure JavaScript is enabled in your browser
- Clear browser cache and Moodle cache
- Try a different browser or clear browser cookies
- Check browser console for errors

### User language preference not saved

- Verify user is logged in (translations require authentication)
- Check that Moodle user preferences are working (`mdl_user_preferences` table)
- Clear your browser cookies for the Moodle domain

## Performance Considerations

- Translations are cached per selection/language combination
- Changing language dropdown automatically invalidates cache
- First translation request may take 2-5 seconds depending on content length and AI provider
- Large documents (>5000 words) may take longer to translate
- Recommend testing with your AI provider's performance characteristics

## Version Compatibility

This plugin is designed for and tested on **Moodle 5.2+** only. It will not work on Moodle 4.7 or earlier versions because:

- The AI framework (`core_ai`) is not available in Moodle 4.7
- The Modal static API may differ in earlier versions
- The hooks system used may not be compatible

If you attempt installation on Moodle 4.7 or earlier, you will encounter dependency errors during installation.

## Limitations

- **Full-page translation** extracts visible text content (may not include all page elements)
- **Selected text** is limited by browser text selection capabilities
- **Large selections** (>10,000 words) may exceed AI provider token limits—users will see an error
- **Real-time collaboration** — Does not synchronize translations across multiple users
- **Offline use** — Requires internet connection for AI provider communication

## Support & Reporting Issues

For bug reports, feature requests, or general support:

1. **Check existing issues** — Visit the [plugin repository](https://github.com/sumitnegi933/moodle-aiplacement_translate/issues)
2. **Report new issues** — Include:
   - Moodle version
   - PHP version
   - Browser and browser version
   - Steps to reproduce
   - Error messages from browser console
   - Screenshots if applicable

3. **Email support** — For sensitive issues, email sumitnegi.933@gmail.com

## Contributing

Contributions are welcome! To contribute:

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/my-feature`)
3. Follow [Moodle coding standards](https://moodledev.io/general/development/policies/codingstyle)
4. Commit with clear messages
5. Push to your fork
6. Submit a pull request with a description of your changes

## License

This plugin is licensed under the [GNU General Public License v3 or later](LICENSE).

```
This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.
```

## Credits

Developed by **Sumit Negi**

Based on Moodle's AI framework and inspired by the Course Assistant placement.

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for version history and improvements.

---

**Last Updated:** July 2026  
**Plugin Version:** 2026.07.22  
**Moodle Version Required:** 5.0+
