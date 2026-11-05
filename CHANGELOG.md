# Changelog

Each "## version" section becomes the release notes your site shows under Cheri Gallery → Settings → Updates.

## 0.10.1

- The Cheri logo at the bottom of a gallery's cover photo is 25% larger, and clicking it goes to your home page (the Website address in Settings).

## 0.10.0

- Featured galleries for your home page. In a gallery's Settings → Who can see it, choose "Featured on the home page" position 1–6, then add `[cheri_galleries featured="yes"]` to the page. You get your picks in that order, three to a row (two on tablets, one on phones), up to 6. Picking a position that's taken moves the other gallery out of it.
- `[cheri_galleries]` gains a `columns` option (1–6). The galleries list in WordPress shows a Featured column.
- The same rules as always: private and closed galleries are never shown.

## 0.9.2

- The gallery page is back to its original photo layout. The spacing fix from 0.9.1 now only applies to galleries embedded on other pages with the shortcode.

## 0.9.1

- Fixes missing space between photos stacked above each other when a theme or Elementor overrides the gallery's spacing.
- Embedded galleries get an 8px gap by default, and a new `gap` option (0–30) sets your own, e.g. `[cheri_gallery id="42" gap="12"]`.

## 0.9.0

- Show a public gallery on any page of your website with `[cheri_gallery id="…"]`. You get the photo grid and the enlarged view with Previous/Next, plus a "View the full gallery" button. Copy the shortcode from the gallery's Share box.
- Options: `set="Ceremony"` (one set), `limit="12"`, `columns="3"` (2–6), `title="yes"` (gallery name above), `link="no"` (hide the button). It works in an Elementor Shortcode widget too.
- Only public, published, open galleries can be embedded. Watermarks still apply, and there are no hearts or downloads in the embed.

## 0.8.2

- New "Watermark the cover photo" switch in each gallery's Settings → Protection. When it's off (the default), the cover photo at the top, the locked page and the gallery card show clean while every album photo keeps its watermark.

## 0.8.1

- The Client sign in page now has an optional Gallery password box. With it, a client of that gallery goes straight in, and someone new adds their first name and confirms by email. Left empty, it emails a sign-in link as before.

## 0.8.0

- On a locked gallery, "Sign in with your email to save favourites" takes an email and the gallery password:
  - A client you've added to the gallery goes straight in, with no email link. You can turn this off in Settings → Pages.
  - Anyone new adds their first name and gets an email link to confirm their address. Once confirmed they're signed in and join the gallery as a guest, and you get an email.
- Guests can view and make their own favourites. They can only download if "Who can download" is set to "Anyone who can view". Guests are listed on the gallery's Client & shoot box, where "Make client" promotes them.
- Admin and editor accounts can never sign in with a gallery password.

## 0.7.0

- A password-protected gallery now opens on its full-screen cover photo with the names and date, and the password box where "View gallery" normally is (plus "Client? Sign in instead"). The other photos stay locked until the password is entered or the client signs in.
- When you're signed in as the photographer, a thin bar on locked galleries explains you're seeing every photo, with a link to preview the locked page visitors see.

## 0.6.0

- Easier linking of clients and galleries. On a gallery, each client is listed with a Remove button, and "+ Add a client…" adds another with one pick (no more Ctrl/Cmd-clicking).
- On a client's page, "Add to a gallery" links them to any gallery (drafts included), and each gallery in their list has a Remove link. Creating galleries stays in the Galleries section.

## 0.5.1

- My galleries: when you're signed in as the photographer you now see every published gallery (clients still see only their own), and the client lookup is more reliable.
- New "Cover photo position" for each gallery (Top, Centre or Bottom) in the "Client & shoot" box. It sets which part of the cover stays in view on the full-screen cover, the gallery cards and the share email.

## 0.5.0

- A gallery now has no header: the cover photo fills the screen right to the top, with your website's footer at the bottom.
- My galleries and Sign out (or Client sign in) sit in the gallery bar just under the cover photo.
- The password, client sign-in and My galleries pages use your website's normal header and footer.
- Settings → Pages lets you choose the header for a gallery and for the other pages separately, and the footer.

## 0.4.0

- Fixes the enlarged photo showing no controls (the photo opened on its own instead). The gallery's script wasn't starting, which also stopped the hearts and favourites drawer working. All now work.
- Header and footer are now chosen separately under Cheri Gallery → Settings → Pages. By default: the gallery's own slim header with the Cheri logo (plus My galleries / Sign out), and your website's footer.

## 0.3.2

- Fixes "GitHub answered with status 403" when checking for updates. Shared web hosts often use up GitHub's 60-requests-an-hour limit for its API, so update checks and downloads now use GitHub's ordinary download links instead.

## 0.3.1

- Settings → Updates now has an "Update to … now" button that installs a new version straight away (checking for updates only looks).
- The Updates panel shows the version on your website, the newest available, when it last checked, and a clear message if your web host can't reach GitHub.
- Update checks can no longer be answered from a stale cache.

## 0.3.0

- Gallery, sign-in and My galleries pages now sit inside your website's own header and footer (menu, logo and footer from your theme). You can switch back to standalone pages under Cheri Gallery → Settings → Pages.
- Clearer controls when a photo is enlarged: a labelled bar with Previous, Favourite, Download, Slideshow and Next, large arrows either side, and a Back to gallery button. They all work on phones too.
- The phone's or browser's Back button now returns to the gallery instead of leaving the page.
- Signed-out visitors see "Sign in to favourite" on enlarged photos.
- The gallery keeps its own look even where the theme styles buttons, links, headings and images differently.

## 0.2.0

First release of Cheri Gallery.

- Client galleries with sets, cover photo, public / password / private visibility and closing dates
- Watermarks (corner, centre, tiled) drawn into the photos
- Whole-album and single-photo downloads, with per-photo hide and no-download
- Client accounts with email sign-in links and a My galleries page
- Favourites lists clients can name, annotate and send to you
- Branded share email with live preview and templates
- Updates itself from GitHub releases
