# Crazy Simple Calendar for WordPress
WordPress Plugin Repository for "Crazy Simple Calendar"!

* Contributors: carterfromsl
* Tags: events, calendar
* Requires at least: 4.7
* Tested up to: 6.2.2
* Stable tag: 1.2
* Requires PHP: 7.0
* License: MIT
* License URI: https://opensource.org/licenses/MIT
* Display your daily schedule with a super simple monthly calendar. Plan your events and manage them easily.

## Description

Crazy Simple Calendar provides a super simple event planning experience with a minimalist monthly calendar.

* Manage events for the year in a straightforward monthly table format.
* Add custom content for each day, which can include HTML.
* Includes predefined CSS classes for easy styling of the calendar and event contents.
* No coding knowledge required for basic functionalities!

### More information
For more information, please refer to the plugin's main PHP file or its CSS files.

## Usage

After activating the plugin, you can add the calendar to any post or page using the [simple_calendar] shortcode. This will display the calendar for the current month with any custom content you've added for each day.

To add or edit the custom content for each day, go to the 'Simple Calendar Settings' menu in the WordPress admin area. You'll find a form where you can enter custom content for each day of the current month. The content can include HTML.

## Styling

## calendar comes with predefined CSS classes to make styling easy:

* .cs-wrap: This class wraps all calendar elements.
* .cs-calendar: This class is added to the wrapper div for the calendar. You can use it to target the table and all elements inside it. For example, you could set the font or width of the table.
* .cs-event: This class is added to the div wrapper for the event content inside each table cell. You can use it to style the event content separately from the rest of the cell content.
* .date: This class is added to the span that contains the day number in each table cell. You can use it to style the day numbers.
* td.today: This class is added to the table cell for today's date. You can use it to highlight today's date in the calendar.
* td.inactive: This class is added to table cells for days that are not part of the current month. You can use it to differentiate these cells from the cells of the current month.
* .cs-title: This class is added to the h2 heading above the calendar.
* .cs-nav: This class is added to the element that wraps the month navigation buttons.

You can add your custom styles to these classes in the Custom CSS section of the plugin settings. For admin styles, you can modify the admin-styles.css file in the plugin directory.

## License

The Crazy Simple Calendar plugin is open-sourced software licensed under the MIT license.
