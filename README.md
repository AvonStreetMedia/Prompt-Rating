# PromptRocket Ratings Plugin

A simple, fun rating system for your PromptRocket posts. Because prompts that don't suck deserve ratings that don't either.

## Features

- **Fun Rating Labels**: From "Total dumpster fire" to "Holy $#!† this works!"
- **Simple One-Click Rating**: No forms, no friction
- **Cookie-Based Duplicate Prevention**: 30-day protection
- **Top Rated Posts Shortcode**: Display your best content
- **Admin Dashboard**: Track ratings and see what's working
- **Fully Responsive**: Works on all devices
- **On-Brand Design**: Uses your PromptRocket colors
- **Per-Post Control**: Disable ratings on specific posts

## Installation

1. Upload the `promptrocket-ratings` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. That's it! Ratings automatically appear on all your posts

## How It Works

### For Visitors
- **Top of Post**: Shows average rating with fun text (e.g., "Actually good (4.2/5)")
- **Bottom of Post**: Click stars to rate from 1-5
- **One-Click Rating**: No login required, instant feedback
- **Cookie Protection**: Can only rate once per post (30 days)

### Rating Scale
- ⭐ = Total dumpster fire
- ⭐⭐ = Kinda sucks
- ⭐⭐⭐ = Doesn't suck
- ⭐⭐⭐⭐ = Actually good
- ⭐⭐⭐⭐⭐ = Holy $#!† this works!

## Shortcodes

Display your top-rated prompts anywhere:

```
[promptrocket_top_rated count="10"]
```

Options:
- `count` - Number of posts to display (default: 10)

## Admin Features

Access via **Posts → Prompt Ratings** in your WordPress admin:

- View top-rated prompts
- See recent ratings activity
- Monitor which content performs best

### Disabling Ratings on Specific Posts

When editing any post, look for the "PromptRocket Ratings" box in the sidebar:
- Check "Disable ratings for this post" to hide ratings
- Useful for announcement posts, about pages, or special content

## Customization

### Changing Star Icons

Currently uses emoji stars (⭐). To change to custom icons:

1. Replace the star HTML in `promptrocket-ratings.php`
2. Update CSS in `assets/ratings.css`
3. Could use:
   - Font Awesome icons
   - Custom SVGs (rockets 🚀, flames 🔥, etc.)
   - PNG sprites for 8-bit style

### Changing Rating Text

Edit the `$rating_labels` array in `promptrocket-ratings.php`:

```php
public static $rating_labels = [
    1 => 'Your text here',
    2 => 'Your text here',
    // etc.
];
```

### Styling

All styles use CSS variables for easy customization:

```css
:root {
    --pr-cream: #F2EDE1;
    --pr-red: #E63946;
    --pr-orange: #F77F00;
    --pr-yellow: #FCBF49;
    --pr-teal: #2A9D8F;
}
```

## Database

Creates one table: `wp_promptrocket_ratings`

Stores:
- Post ID
- Rating value (1-5)
- IP address (for analytics)
- Timestamp

## Performance

- Ratings cached for 1 hour
- Lightweight AJAX submission
- Minimal database queries
- No external dependencies

## Troubleshooting

**Ratings not showing?**
- Check if you're on a single post page
- Ensure JavaScript is enabled
- Clear cache if using caching plugin

**Can't rate?**
- Check if cookies are enabled
- May have already rated (check for cookie `pr_rated_[post_id]`)

**Styles look wrong?**
- Clear cache
- Check for CSS conflicts with theme

## Future Features

Possible additions:
- User account integration
- Rating breakdown by category
- Email notifications for low ratings
- Export ratings to CSV
- GraphQL support
- Rating widgets

## Support

For issues or questions, hit up PromptRocket.io

## Version History

### 1.0.1
- Added "Prompt Rating" H2 title above ratings
- Removed red border from rating display
- Increased font size to 16px for better readability
- Added ability to disable ratings on specific posts
- Fixed shortcode to show posts with 1+ ratings (was 3+)
- Improved styling consistency

### 1.0.0
- Initial release
- Basic rating system
- Admin dashboard
- Top rated shortcode

---

**Remember**: Keep it simple. Ship it. Make money. Then iterate.

*Built for tired brains and crying babies in the background.*
