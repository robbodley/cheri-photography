# Cheri Gallery

Client galleries for **Cheri Photography**: a WordPress plugin that replaces Pixieset for delivering photos, styled to match the Cheri Photography website (Marcellus and Open Sans, tan and cream, the Cheri logo and C-and-heart mark).

The plugin is in [`cheri-gallery/`](cheri-gallery/). The look comes from the Cheri Gallery design system.

## What it does

**For you (WordPress admin → Cheri Gallery)**

- **Galleries**: one per client job. Upload photos by drag and drop, split them into sets (Ceremony, Portraits…), pick the cover, and hide or block downloads on single photos. You can also select many photos at once.
- **Who can see it**: Public, Password (case-sensitive, made for you from the couple's initials and date, e.g. `er1505`) or Private (only from a secret link or by the signed-in client). You can set a closing date.
- **Downloads**: separate switches for *whole album* (zip) and *single photos*; full resolution or web size; signed-in client only or anyone who can view.
- **Watermarks**: Off, Corner, Centre or Tiled, drawn into the photos themselves so screenshots carry them. Originals stay clean and private.
- **Clients**: add a client with a name and email, see their galleries, last visit and every favourites list, with **Copy filenames** for Lightroom. A "New" badge appears when someone sends a list.
- **Share**: a branded "your photos are ready" email with a live preview. It goes to you by default so you can forward it, with templates, the password box, the client sign-in link and a test send.

**For your clients**

- A full-screen cover, then a masonry photo grid with set tabs, a lightbox (swipe on phones), a slideshow and share.
- **Sign in by email link**: no password to remember. Signed in, they see **My galleries** at `/my-galleries/`.
- **Favourites lists**: heart photos into named lists ("Album picks", "Prints for Mum"), add a note, and **Send to Cheri**. You get an email.

## Installing

1. Download `cheri-gallery.zip` from the [latest release](https://github.com/robbodley/cheri-photography/releases/latest).
2. In WordPress: **Plugins → Add New → Upload Plugin**, choose the zip, then **Activate**.
3. Go to **Cheri Gallery → Settings**: check the email address (`photome@cheri.photography`), add your Instagram link, and press **Save**.
4. **Set up an email sender** (needed for sign-in links and gallery emails): install **WP Mail SMTP**, connect the mailbox that hosts photome@cheri.photography, add the SPF/DKIM records it gives you, and use **Send test** on the settings page.
5. If gallery links show "Page not found", go to **Settings → Permalinks** and press **Save** once.

Requirements: WordPress 6.2+, PHP 7.4+ with the GD or Imagick, zip and exif extensions (standard on most hosts). Uploads larger than your host's `upload_max_filesize` are refused with a clear message; ask your host to raise the limit if full-resolution JPEGs are bigger.

## Updating

The plugin updates itself from this repository's GitHub Releases, like any plugin from wordpress.org:

- WordPress checks twice a day. When there's a newer version, **Plugins** shows **Update now**. Click **Enable auto-updates** next to Cheri Gallery to have it install on its own.
- **Cheri Gallery → Settings → Updates** shows the installed and latest versions, the release notes, and a **Check for updates now** button.
- Updates replace only the plugin's code. Photos (in `wp-content/uploads/cheri-gallery/`), clients, favourites and settings stay as they are, and any database changes a new version needs are applied automatically.
- The repository is public, so no token is needed. If it's ever made private, add a read-only GitHub token under Settings → Updates.

### Publishing an update (developer)

1. Change the code and bump the version in **both** places in `cheri-gallery/cheri-gallery.php` (the `Version:` header and `CG_VERSION`).
2. Add a `## x.y.z` section to `CHANGELOG.md`. It becomes the release notes shown in WordPress.
3. Get it onto the repository's default branch (push or merge a pull request). The **Release plugin** GitHub Action checks the two version numbers match, syntax-checks the PHP, builds `cheri-gallery.zip` and publishes release `vx.y.z`. If that version is already released it does nothing, so ordinary pushes without a version bump never publish anything.

## Pages and addresses

| Address | What |
|---|---|
| `/gallery/<name>/` | A client gallery |
| `/client-login/` | Client sign-in (email link) |
| `/my-galleries/` | A signed-in client's galleries and favourites |
| `[cheri_gallery id="42"]` | One public gallery's photos on any page. See [Shortcodes](#shortcodes) |
| `[cheri_galleries]` | Cards for galleries marked "Show on the galleries page". See [Shortcodes](#shortcodes) |

## Shortcodes

Paste these into any page or post, or into an Elementor **Shortcode** widget. Options go inside the brackets, in any order, e.g. `[cheri_gallery id="42" set="Portraits" columns="3" gap="12" title="yes"]`.

### `[cheri_gallery]`: show one gallery's photos on a page

The photo grid plus the enlarged view (Previous / Next, Slideshow, Back), with a "View the full gallery" button underneath. It's view only (no hearts or downloads) and the gallery's watermark still applies. Copy the shortcode from the gallery's **Share** box.

| Option | What it does | Default | Example |
|---|---|---|---|
| `id` | **Required.** Which gallery (the number in its shortcode or edit link) | none | `id="42"` |
| `set` | Show only one set, by its name exactly as written in Photos | all sets | `set="Ceremony"` |
| `limit` | Show only the first so many photos | all photos | `limit="12"` |
| `columns` | Columns on a computer, from 2 to 6 (tablets show at most 3, phones at most 2) | `4` | `columns="3"` |
| `gap` | Space between photos in pixels, from 0 to 30 | `8` | `gap="12"` |
| `title` | Show the gallery name above the photos | `no` | `title="yes"` |
| `link` | Show the "View the full gallery" button | `yes` | `link="no"` |

Only **public, published** galleries that haven't passed their closing date can be shown. For anything else visitors see nothing, and you see a short note when signed in.

### `[cheri_galleries]`: a list of gallery cards

A grid of cards (cover photo, session type, name, date and number of photos) linking to each gallery. It only includes galleries with **Show on the galleries page** switched on in their Settings, and never private ones.

| Option | What it does | Default | Example |
|---|---|---|---|
| `featured` | `yes` shows only your featured galleries (Settings → Who can see it → **Featured on the home page**, positions 1–6), in that order, instead of the "Show on the galleries page" ones | `no` | `featured="yes"` |
| `type` | Only one session type, by its shortcode value (listed in Settings → Session types, e.g. `wedding`, `portrait`, `newborn`, `corporate`) | all types | `type="portrait"` |
| `limit` | Most cards to show | `24` (`6` when featured) | `limit="3"` |
| `columns` | Cards per row on a computer, from 1 to 6 (tablets show at most 2, phones 1) | fit to width (`3` when featured) | `columns="3"` |

For the home page: `[cheri_galleries featured="yes"]` shows up to 6 featured galleries, three to a row. Picking a position another gallery already has moves that gallery out of it.

## Look and layout

A gallery has no header by default (the cover photo reaches the top; My galleries / Sign out sit in the bar under it). The password, sign-in and My galleries pages use your website's header. All pages use your website's footer. Each can be switched under Settings → Pages. All gallery styles are scoped under a `.cg-page` wrapper and load after the theme's, so theme button and heading styles don't change the gallery. The stylesheet the site loads, `assets/css/front.css`, is generated from `components.css` + `gallery.css`: after editing those, run `python3 tools/build-front-css.py`.

## How the protection works

- Photos are stored in `wp-content/uploads/cheri-gallery/` under random file names, with an `.htaccess` that blocks direct access. Every photo and download goes through WordPress, which checks the gallery's visibility, the client's sign-in and the download switches first.
- Password and private-link access is remembered for 30 days with a signed cookie. Changing the password or private link locks out everyone using the old one. Password attempts are limited to 10 per 10 minutes.
- Gallery passwords are stored readable, because they go into the share email (as Pixieset does). Client accounts have no passwords at all: they sign in with one-time links that expire after an hour.
- Galleries are kept out of search engines and sitemaps, except public ones.
- The cover photo chosen for an email gets a public 1200px copy, because email programs can't sign in.

## Not built yet

- Download packs and payments (Stripe): planned next.
- Drag-to-reorder photos (photos show in upload order).
- Wedding album selection with a set number of photos (favourites lists cover this for now).

## Testing status

Every PHP file passes a syntax check. The real templates, CSS, JavaScript and image and watermark code were rendered and clicked through in a browser with sample photos. That included the gallery, lightbox, favourites drawer, password page, sign-in, My galleries, the email, and the admin photo manager, settings, clients and Share screens. **It has not yet been run inside a live WordPress install**, so please try it on a staging site (or a fresh test install) before your real galleries. The checklist:

1. Activate; create a client; create a gallery, pick the client, upload 10–20 photos into two sets; publish.
2. Open the gallery link in a private window: the password page appears; a wrong password gives the error; the right one opens it.
3. Try each watermark style and check the photos change.
4. Share → Send test to me: the email arrives (not in spam) and its links work.
5. As the client (use your own second email): sign in by link, heart photos, make a second list, add a note, send. The alert email arrives and the list shows under Clients with "New".
6. Download a photo and the whole album; then block a photo's download and check it's left out.
7. Set the gallery to Private and check only the private link (or the signed-in client) opens it.
