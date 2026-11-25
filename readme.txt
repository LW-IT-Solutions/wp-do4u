=== do4u.eu ===
Contributors: lwitsolutions
Tags: ai writing, content, seo, woocommerce, alt text
Requires at least: 6.4
Tested up to: 7.2
Requires PHP: 8.1
Stable tag: 1.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

do4u.eu tools in wp-admin for posts, pages, media and comments, and with WooCommerce texts for many products at once. Started only by you.

== Description ==

do4u.eu connects your site to your do4u.eu account (https://do4u.eu). One plugin, one connection, two kinds of tools:

* **Writing and checking tools** for posts, pages, media, comments and the website. Every tool opens inside wp-admin, already filled in with the right content. You check the form and start the job yourself, because a job uses credits. Results stay suggestions until you take them over.
* **Shop tools** for WooCommerce, if it is active: product descriptions, short descriptions, SEO titles, meta descriptions and image alt texts for many products in one batch, with a free estimate, a review of every field and undo.

Your content comes directly from the WordPress database, not through your public website. So the writing tools also work on sites that do4u.eu cannot read from outside: on staging, behind a login or on a server do4u.eu may not call. The few tools that know nothing but an address say so.

Where you find the tools:

* do4u.eu > All tools: the tools with a use in WordPress, and where each one is found. do4u.eu tools without a use in WordPress are not part of the plugin.
* do4u.eu > All tools also writes new texts from your facts: job ad, invitation, company profile, notice, donation appeal and release notes. You can save the result as a new draft.
* do4u.eu > Newsletter: choose posts and pages with search and multiple selection, set the main article, and the newsletter tool opens filled in with their addresses, titles, excerpts and featured images.
* do4u.eu > Shop (with WooCommerce): batches of product texts, SEO texts and alt texts, their review, and the history with undo.
* do4u.eu > Overview and the dashboard box: balance, budget of the site, shortcuts.
* Posts and pages: a sidebar in the block editor and a box in the classic editor with Summarize, Rewrite, Proofread, Translate, SEO title and description, Headlines, FAQ and schema, Social posts, Internal links, Explain terms, Language level and Check accessibility. Unsaved changes are included.
* From a post or page: press release, checklist, questions to the text and contradiction check. A press release can be saved as a new draft; the original stays as it is.
* Post and page lists: the row action and bulk action "Create newsletter" open the newsletter page with the selected posts; the first one is the main article.
* Media library: alt text in the list row, on the edit page and in the media dialog.
* Comments: row actions to check a comment and to get a reply suggestion.
* Tools > Website checks: accessibility, website check, links, structured data and more, for your home page or a page you choose.
* WooCommerce product list: the bulk action "Create texts with do4u.eu" opens the shop tools with the selected products.
* WooCommerce product page: a box that writes the description of this one product, shows suggestions waiting for it and its last changes by do4u.eu.

How results are taken over:

* Nothing happens without your click, and nothing is ever published.
* Text goes into a field that you then save yourself, or into a new draft. The original post stays as it is.
* Existing content is never replaced silently: the plugin shows what is in the field and asks first.
* With Yoast SEO or Rank Math active, the SEO tool fills their SEO title and meta description, and you save them with the post.
* Results count as untrusted. On the server, every takeover checks a nonce and your rights for that item, and cleans the text (sanitize_text_field, wp_kses_post).

The shop tools in detail:

* **Product texts**: description, short description, and on request a new product name and a meta description. Written only from the data of the product: name, categories, attributes, weight, dimensions, tags and the existing texts. Every number that is not in this data is marked.
* **SEO title and meta description**: three variants per product within the usual length limits, using the focus keyword of your SEO plugin if one is set.
* **Image alt texts**: for the main image and, on request, the gallery images of each product, by default only for images without alt text.
* **Texts from the product photo**: for products with little text, do4u.eu looks at the main image and writes description, short description, name and image alt text. Sizes, materials, certificates and similar facts appear only if they are in your product data.

The way of a batch:

1. Go to do4u.eu > Shop, or select products in the product list and choose the bulk action "Create texts with do4u.eu".
2. Choose what to write, the fields to fill, tone, length, language (25 languages) and whether the text is for your shop or a marketplace listing. Filter the products by category, status, empty description or search.
3. Get a free estimate. The page shows what will be sent for each product.
4. Start the batch. The page shows the progress; you can leave it and come back.
5. Compare the current value and the suggestion of every field. Fields with a warning (new numbers, a web or mail address that is not in your data, over the length limit, changed in the shop meanwhile) are not ticked. Apply what you want, field by field, per product or all at once.
6. Every change is kept in the history of the product. Undo restores the old values, and asks first if the field was changed again since.

Where the shop texts go:

* Description, short description and name through the WooCommerce product object, like a normal save.
* SEO title and meta description into Yoast SEO, Rank Math, SEOPress or The SEO Framework if one of them is active. Otherwise into own fields of the product, which the plugin can use for the document title and the meta description tag of product pages (off by default).
* Alt texts into the alt text of the image in the media library.

The plugin adds nothing to the public part of your website: no scripts, no styles, no cookies for visitors. The only exception is the optional output of the own SEO fields on product pages, which is off by default. WooCommerce is not required; without it the shop tools do not appear. They work with the order tables of WooCommerce (HPOS) and the block checkout, which they do not touch.

The interface is in English and can be translated on translate.wordpress.org; the tools inside the iframe follow your WordPress user language in the 26 languages of do4u.eu, otherwise English.

== Installation ==

1. In WordPress, go to Plugins > Add New Plugin, search for do4u.eu, install and activate it.
2. Your do4u.eu account is required; registration is free at https://do4u.eu.
3. In your do4u.eu account, create an API key at https://do4u.eu/api-keys.
4. In WordPress, go to do4u.eu > Settings as an administrator, paste the key and click Connect.

Connecting creates a widget for your site in your do4u.eu account: all tools, session mode, allowed only on the address of your wp-admin, with a budget of 100 credits per day and 1,000 per month that you can change under Settings. wp-admin must run on https, because do4u.eu allows embedding on https addresses only. The shop tools need only the API key, not the widget.

Only administrators can see the settings. Anyone who can edit posts can use the writing tools; the filter do4u_wp_capability changes that. Users with the capability manage_woocommerce (shop managers) can use the shop tools; the filter do4u_shop_capability changes that. Rights for each item are checked in addition.

If you used the separate plugin "do4u.eu for WooCommerce" before: its tools are now part of this plugin. Deactivate and delete it; this plugin takes over its API key and its defaults once.

== Frequently Asked Questions ==

= What does a job cost? =

Every job you start uses credits from your do4u.eu account, at the prices shown on https://do4u.eu/en/pricing. The form shows an estimate. Opening and filling in a tool is free. If do4u.eu cannot complete a job, it costs nothing. The budget of the site limits what all users of the site can use together in the writing tools; alt text jobs and shop batches are the exception, see Limits.

= What does a shop batch cost? =

Batches use credits of your do4u.eu account at the same prices. The estimate is free. When the batch starts, do4u.eu reserves the estimate and charges what the results really use. Entries that fail or are cancelled before they run cost nothing.

= How many products can one batch hold? =

As many as your do4u.eu package allows per batch (Free: 20). Set "Entries per batch" under do4u.eu > Shop > Settings to the same number. For a large catalogue, create one batch per page of the product choice.

= How long does a batch take? =

Usually minutes. do4u.eu runs batches with lower priority than single jobs, so at busy times it can take longer. You can close the page; the batch keeps running and its suggestions wait for you.

= Do the product texts go live immediately? =

No. Nothing changes in your shop before you apply a suggestion. Applied texts are saved like an edit in the product screen; a published product shows them right away, so check them first. Every change can be undone under do4u.eu > Shop > History or from the box on the product page.

= Why did alt texts or texts from the photo fail in a batch? =

do4u.eu loads each image of a batch from its public address. Images on a local, private or password-protected site cannot be read; those entries cost nothing.

= Which tools need a public address? =

Tools that know nothing but an address, because do4u.eu has to read the page itself: website check, broken link check, image check, social card check, hreflang check, duplicate content check, llms.txt generator, find baked-in image text, alt text checker and verified domains. Pages behind a login, a password or in a private network cannot be read this way. The plugin marks these tools. All other writing tools get your content from WordPress. In the shop tools, the images of a batch need a public address.

= Why are newsletters only made from published posts? =

A newsletter needs links that work for your readers. Drafts and password-protected posts are left out, and the page says so. Each article goes along with its title, excerpt and featured image, so do4u.eu can write about it even if it cannot read the page itself. The image appears in the mail if its address is public and uses https.

= My site uses a Content Security Policy. =

Allow https://do4u.eu in frame-src and script-src for wp-admin.

= Does it work with SEO plugins? =

The SEO tool of the editor fills SEO title and meta description of Yoast SEO and Rank Math. The shop tools write SEO title and meta description of products into Yoast SEO, Rank Math, SEOPress or The SEO Framework, and use their focus keyword.

= Which product types do the shop tools handle? =

Simple, variable, grouped and external products. Variations are not changed; their parent product is.

= In which languages are product texts written? =

Product texts, SEO texts and texts from photos in 25 languages: English, German, Polish, French, Spanish, Italian, Dutch, Czech, Slovak, Ukrainian, Bulgarian, Danish, Greek, Estonian, Finnish, Irish, Croatian, Hungarian, Lithuanian, Latvian, Maltese, Portuguese, Romanian, Slovenian and Swedish. Alt texts in a batch are written in English, German or Polish.

== Screenshots ==

1. All tools, with the place where each one is found in WordPress.
2. Shop: choose what to write and the products, with the state of their texts.
3. Shop: free estimate, with everything that will be sent.
4. Shop: review of every field, current value and suggestion, with warnings.
5. Shop: history with undo.
6. Product page: the do4u.eu box with suggestions waiting and the last changes.
7. Settings: one connection for all tools.

== External services ==

This plugin uses the do4u.eu service (https://do4u.eu), operated by the provider named in the legal notice on https://do4u.eu. Processing takes place in the EU.

do4u.eu terms of service: https://do4u.eu/en/legal/terms · privacy policy: https://do4u.eu/en/legal/privacy · data processing agreement: https://do4u.eu/en/legal/dpa · legal notice: https://do4u.eu/en/legal/imprint · trust center: https://do4u.eu/en/trust · prices: https://do4u.eu/en/pricing

The plugin reads your content from the WordPress database. It sends no requests to your own website, neither itself nor through do4u.eu, except that the tools listed above as needing a public address are given the address you see in their form, and that do4u.eu loads the images of a shop batch from their public addresses.

From your WordPress server to https://do4u.eu/api/v1/, always with your API key in the Authorization header and an own user agent, which does not contain the address of your site:

* When an administrator connects or changes the budget: GET /balance and GET /widgets to check the account; POST /widgets with a name that contains the host name of your site, its address (origin), the budget and the setting that all tools are allowed; DELETE /widgets/{id} for a replaced widget.
* When anyone opens a tool: POST /widgets/session with the widget id, the name of the tool and your numeric WordPress user id as "subject". do4u.eu stores only a hash of that id, so that one user cannot see the results of another.
* When you click start in the alt text dialog: POST /jobs with the image file from your media library, its file name and title, and your language; then GET /jobs/{id} until the result is there. This job is set to "do not store".
* On the overview page and in the dashboard box: GET /balance and GET /widgets/{id}. On the tools page: GET /tools and GET /widgets, cached for six hours. On the pages of the shop tools: GET /balance, at most every five minutes.
* Shop tools, when you ask for an estimate: POST /batches with "estimate_only": true. When you start a batch: the same request with "estimate_only": false. Both contain the chosen tool, the options (tone, length, channel, language) and one row per product or image:
  * for product texts and SEO texts: the product name, categories, attributes, weight, dimensions, tags, the current short description and description as plain text, and for SEO texts the focus keyword of your SEO plugin;
  * for alt texts: the public address of the image, its file name, the product name and the image title;
  * for texts from the photo: the public address of the main image and the product name, categories, attributes, weight, dimensions, tags and short description.
* Shop tools, while a batch runs (every 15 seconds while its page is open, or when you click "Check now"): GET /batches/{id}. When it is done: GET /batches/{id}/result.csv. When a batch is cancelled or deleted, and after the import if "Delete a batch at do4u.eu as soon as its results are imported here" is on (default): DELETE /batches/{id}. do4u.eu keeps the results of a batch until they are deleted this way, at the latest after its storage period (7 days at the time of writing).
* When an administrator disconnects, or when the plugin is deleted: DELETE /widgets/{id}.

From your browser to https://do4u.eu, only after you open a writing tool, not when you merely view a list or an editor:

* The widget loader https://do4u.eu/js/do4u-widget.js and an iframe with the do4u.eu form and its files, with your IP address, browser data, the origin of wp-admin and your language. No do4u.eu cookie is used. The session token travels in the address fragment and is valid for 60 minutes, for this tool and this user.
* The content that fills the form stays in your browser until you start the job. Depending on the place these are: the current text or HTML of the post (block comments and shortcodes removed, shortcodes not executed), its title, its address and the addresses and titles of up to 200 public posts and pages for internal links; addresses, titles, excerpts and featured image addresses of the selected posts for a newsletter; the text of a comment, without the name of its author; title, descriptions and categories of a product; the saved content of the checked page as HTML, or for address tools its address.
* When you start a job, what is in the form at that moment goes to do4u.eu, together with the session token. The site widget is set to "do not store": do4u.eu deletes the submitted content when the job is done and the result after it has been fetched, at the latest after one hour. Your do4u.eu account keeps the job itself (tool, time, credits) without its content.

The shop tools run between your server and do4u.eu only; your browser does not contact do4u.eu for them. The plugin sends no data about visitors, customers or orders of your website. It sends comment text only if you open a comment tool and start the job.

== Privacy ==

In WordPress, the plugin stores:

* the API key, encrypted with the WordPress salts (changing the salts requires saving the key again);
* the id, address and budget of the site widget;
* the list of tools for six hours, a notice for one minute, and for two hours which user started an alt text job;
* for drafts it created, a reference to the original post in the custom field _do4u_wp_source;
* for the shop tools: their settings, the newest 50 batches with the data sent, the suggestions and what was applied, for every product it changed a history of the last 20 changes with the old and new values, a list of the last 200 changes of the site, the own SEO title and meta description fields if no SEO plugin is active, the balance for five minutes and a notice for the next page view.

Deleting the plugin revokes the widget at do4u.eu as far as it can be reached and removes all of this, on every site of a network. Drafts, product texts, names and alt texts you took over remain, as do values written into an SEO plugin.

== Limits ==

* Writing tools need JavaScript. The product box works in the classic product editor only.
* Tools that get your content from WordPress see what is stored there, not the theme, shortcodes or dynamic blocks. Shortcodes are removed, never run, and the_content is not applied, because that would run the code of other plugins.
* Alt text jobs in the media library run on your server, not in the iframe, because the form there cannot be given a file. They use the API key directly, so the budget of the site does not limit them. Images must be JPEG, PNG, WebP or GIF of at most 5 MB, stored on this server; a smaller size of the image is sent if the original is too large.
* Shop batches use the API key directly too, so the budget of the site does not limit them; the estimate shows the price before you start.
* A newsletter takes up to 8 articles.
* Rewrite, Proofread and Language level work on plain text: their results have no headings or links. Translate keeps the HTML.
* An HTML result can contain links. The plugin removes scripts and unsafe addresses but does not judge link targets, so check every draft before you publish it. Shop results never contain HTML or links from do4u.eu: the plugin builds paragraphs and lists itself.
* Changing the budget replaces the widget, because do4u.eu cannot edit one. Tools that are open must be opened again.
* If the address of your site changes, connect again: the browser would otherwise refuse the writing tools.
* Shop tools: variations get no own texts, the parent product does; category descriptions are not written; images must be reachable from the internet; WooCommerce 9.0 or newer.
* Advanced: wp-config.php may define DO4U_API_BASE. Only https addresses on do4u.eu ending in /api/v1/ are accepted.
* Developers: the filter do4u_shop_product_text changes the product facts that a shop batch sends to do4u.eu.

== Changelog ==

= 1.1.0 =
* New: shop tools for WooCommerce under do4u.eu > Shop: batches of product texts, SEO title and meta description, image alt texts and texts from the product photo; free estimate; review with warnings and per-field selection; history with undo; bulk action in the product list; suggestions and history in the product box; support for Yoast SEO, Rank Math, SEOPress and The SEO Framework. They replace the separate plugin "do4u.eu for WooCommerce" and use the same connection.
* New: link to the settings in the plugin list.
* Without https, connecting keeps a valid API key for the shop tools and says that the writing tools need https.

= 1.0.0 =
* The SEO tool fills SEO title and meta description of Yoast SEO and Rank Math, if active.
* New: do4u.eu tools inside wp-admin through the do4u.eu widgets, at the place where each one is useful.
* New: newsletter page with a selection of posts and pages.
* New: tools at the right places: editor sidebar and box, post and page lists, media library and media dialog, comments, website checks, WooCommerce product, dashboard.
* New: content comes from the WordPress database, so the tools work on protected and staging sites.
* New: session mode: the API key stays on the server, the browser only gets short-lived tokens.
* Renamed to do4u.eu.
* Removed: the old workbench with five tools and its stored previews. The API key is kept.

= 0.2.0 =
* First public version. API address https://do4u.eu/api/v1/ (adjustable with DO4U_API_BASE), own user agent, readme.txt, uninstall also removes locks and notices.

= 0.1.0 =
* Private preview for testing.

== Upgrade Notice ==

= 1.1.0 =
Adds the shop tools for WooCommerce. If "do4u.eu for WooCommerce" is installed, deactivate and delete it after the update.
