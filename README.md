WP Site Snippets
=========

Wordpress plugin that create site snippets based on domain name

Version
----

0.1

Installation
--------------

1. Download zip or clone the repo
2. Extract (for zip file) and move the ``wp-site-snippets`` folder into your plugins folder ``wp-content\plugins\`` 
3. Go to the site dashboard and activate the plugin.

Usage
--------------

NOTE: This plugin is dependant of the Wordpress MU Domain Mapping Plugin. Make sure that this plugin is activated and setup properly on the site where you will use the snippet.

After the plugin has been activated, a new Dashboard Panel called ``Site Snippets`` will be added. Here, you can add snippets that will be loaded to the site.

#### Adding A Snippet 
To add new content go to ``Site Snippets > Add New Snippet``. Add the snippet content on the text area (statcounter code etc).

If Wordpress MU Domain Mapping Plugin has been setup correctly and the site has been mapped to domains already, A metabox should appear contaning the list of domains and position where this snippet will be added.

Select the domain, where you want the snippet to appear and select the position that this will be added. Select ```wp_head``` if you want it to appear on the ```<header>``` tag or ```wp_footer``` if you want it to appear before the ```</body>``` tag.

NOTE ON POSITION: Some browsers automatically rearrange the tags if trying to attach a snippet with an invalid tag for that position e.g. "CUSTOM SNIPPET" may not appear on the right place (gets inserted inside ```<body>```) since it's not a proper HTML tag. Remember to supply the correct snippet code e.g. ```<script>```, ```<style>``` etc. in order for them to appear on their desired places.



