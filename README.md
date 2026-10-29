# Herald

**Bulk email and newsletters for Flarum, the way Invision Community does it.**

Write one message in your forum's own editor, or design a newsletter from
blocks with the drag-and-drop builder. Drop in quick tags like `{member_name}`
and `{suite_name}`, choose who gets it, preview it as a member will see it, and
send it now or on a date — once, or every day, week or month. Every member gets
their own copy with their own name, join date and post count filled in, sent in
the background, in batches your mail provider can cope with, with a record of
what happened to each one. Guests can sign up too, from a widget.

[![Flarum](https://img.shields.io/badge/Flarum-2.0-orange)](https://flarum.org)
[![Licence](https://img.shields.io/badge/licence-MIT-blue)](LICENSE)
[![PHP](https://img.shields.io/badge/PHP-8.3%2B-777bb4)](https://www.php.net)

![Composing a mailing: the subject and message on the left, the Quick Tags panel on the right](screenshots/compose.png)

---

## Contents

- [What you get](#what-you-get)
- [Installation](#installation)
- [Writing and sending a mailing](#writing-and-sending-a-mailing)
- [The newsletter builder](#the-newsletter-builder)
- [Scheduling and repeats](#scheduling-and-repeats)
- [Templates](#templates)
- [The signup widget and guest subscribers](#the-signup-widget-and-guest-subscribers)
- [Delivery records](#delivery-records)
- [Quick tags](#quick-tags)
- [Choosing recipients](#choosing-recipients)
- [Consent and unsubscribing](#consent-and-unsubscribing)
- [How sending works](#how-sending-works)
- [Works with your editor](#works-with-your-editor)
- [Settings](#settings)
- [Permissions](#permissions)
- [What the email looks like](#what-the-email-looks-like)
- [For developers: adding tags and filters](#for-developers-adding-tags-and-filters)
- [Troubleshooting](#troubleshooting)
- [Requirements](#requirements)
- [Licence](#licence)

---

## What you get

If you have used **ACP → Members → Bulk Mail** in Invision Community, you
already know how Herald works. It follows the same model and uses the same tag
names.

- **A list of every mailing**, with its status (draft, scheduled, sending,
  sent, cancelled), how many it reached, what failed, and when the next one
  goes. Each one can be edited, previewed, copied, saved as a template, sent
  again or deleted.
- **Your forum's own editor** for a quick mass email. Markdown, FoF Rich Text
  or Scribe: whatever your members write posts with, you write mailings with,
  toolbar and all.
- **A drag-and-drop newsletter builder** for when you want something that looks
  designed: a hero, text, images, buttons, social links, your latest
  discussions and a sign-off, with a live desktop and phone preview of the real
  email.
- **Send now, or on a date,** once or repeating daily, weekly or monthly in the
  time zone you choose.
- **Templates,** including two starters: *Weekly digest* and *Announcement*.
- **A newsletter signup widget** for Bespoke and FoF Forum Widgets. Members
  subscribe with one click; guests confirm their address first.
- **A delivery record for every recipient,** with automatic retries, a
  *Resend to failed* button, and sending limits per minute, hour and day.
- **Quick tags**, clicked into place from a panel beside the editor, in the
  subject line or the message.
- **Recipient filters:** groups to include or leave out, join date, last visit,
  post and discussion counts, suspended members. A live count updates as you
  change them.
- **Preview as yourself.** The email exactly as it will arrive, with your own
  details where the tags are.
- **Send a test to yourself** before anyone else sees it.
- **A count before the button.** The last step says how many people the email
  is about to reach, because an email cannot be recalled.
- **Background sending in batches**, with a progress bar and a Cancel button.
  It works on any host: with a queue worker, with cron, or with neither.
- **One-click unsubscribe** in every email, including the `List-Unsubscribe`
  headers that Gmail and Yahoo now require from bulk senders.
- **A switch on each member's Settings page** to opt out of, or back in to,
  news from the forum team.
- **Fully translatable.** Every word on screen and in the email comes from the
  locale file.

![The list of mailings, with a draft and a sent mailing](screenshots/list.png)

## Installation

```bash
composer require ernestdefoe/herald
php flarum migrate
php flarum cache:clear
```

Enable **Herald** in the admin panel. Then open **Bulk mail** from your avatar
menu, or use the **Open Herald** button on the extension's settings page.

### Updating

```bash
composer update ernestdefoe/herald
php flarum migrate
php flarum cache:clear
```

Updating from 1.x keeps every mailing, filter and opt-out as it was, and
unsubscribe links in emails you already sent keep working. 2.0 adds columns to
the mailings table and two new tables (guest subscribers and delivery records),
so do run `php flarum migrate`.

### Before your first real mailing

1. **Check your forum's mail settings.** Send yourself a test from
   **Admin → Email** first. Herald sends through the same mailer as every other
   email your forum sends.
2. **Make sure sending can continue in the background.** See
   [How sending works](#how-sending-works). Without a queue worker or cron, the
   progress page has to stay open until the send finishes.

## Writing and sending a mailing

1. **Bulk mail → Create new.** (Or **Design with the builder** — see
   [The newsletter builder](#the-newsletter-builder).)
2. **Content tab:** write a subject and a message. Click any quick tag to insert
   it where your cursor is, in either field. The optional **Preview text** is
   the line most inboxes show after the subject.
3. **Recipients tab:** narrow down who gets it. The count on the right updates
   as you go.
4. **Preview** to see the finished email with your own details filled in.
5. **Send test to me** to check it in a real inbox.
6. **Send…** shows how many members it will reach. Confirm, and it starts
   sending — or switch to **Schedule** to pick a date (see
   [Scheduling and repeats](#scheduling-and-repeats)).

A mailing can be sent again later. **Send again…** goes to everyone who matches
the filters at that moment, the way Invision's resend does. **Copy** makes a new
draft from an existing mailing, for anything you send regularly.

| Preview | Confirm |
| --- | --- |
| ![The preview, filled in with the admin's own details](screenshots/preview.png) | ![The confirmation, showing how many members the mailing will reach](screenshots/confirm.png) |

**Preview** shows the email at desktop or phone width, filled in with your own
details. Type a username into **Preview as…** to see it with that member's
details instead.

## The newsletter builder

For a quick announcement, the editor is all you need. For a newsletter that
looks designed, press **Design with the builder** — on the Bulk mail list, or on
any new mailing. Whatever you had already written moves into a Text block.

![The builder: blocks on the left, the email in the middle, the selected block's settings on the right](screenshots/builder.png)

- **Drag a block** from the list on the left onto the email, or click it to add
  it below the block you have selected.
- **Drag blocks to reorder them.** From the keyboard: select a block and press
  **Alt + ↑ / ↓** to move it, **↑ / ↓** to move between blocks, and **Delete**
  to remove it. Screen readers hear where it went.
- **Click a block** to change it on the right: its own settings, then the
  spacing, background colour and *Hide on phones* every block has.
- **Undo and redo** with the buttons or **Ctrl/Cmd + Z** and
  **Shift + Ctrl/Cmd + Z**.
- **It saves itself** a couple of seconds after each change.
- **Needs attention** lists anything that would go out broken: no subject, an
  image without a description, a button without a link.
- **Desktop preview** and **Mobile preview** show the real email, rendered on
  your server by the same code that sends it — not a drawing of it.

| Desktop preview | Mobile preview |
| --- | --- |
| ![The real email at desktop width](screenshots/builder-preview.png) | ![The same email at phone width](screenshots/builder-mobile.png) |

### The blocks

| Block | What it is |
| --- | --- |
| **Hero** | A big heading, a few words, an optional image and a button. |
| **Text** | Paragraphs with **bold**, *italic* and links. |
| **Image** | One picture, optionally a link, at a width you choose. |
| **Image + text** | A picture beside a heading and some text. Stacks on phones. |
| **Button** / **Buttons** | One call to action, or up to four in a row. |
| **Divider** / **Spacer** | A line, or empty space. |
| **Social links** | Links to your website, Discord, YouTube, Mastodon and others. |
| **Latest discussions** | Your newest, most active or trending discussions, filled in when it sends. |
| **Sign-off** | Who it is from. |

Every email ends with the **unsubscribe footer**. It is shown locked at the
bottom of the canvas and cannot be removed.

Text in the builder is written as `**bold**`, `*italic*` and
`[link text](https://…)`, with the buttons above the box doing it for you.
Quick tags work in builder text, headings and links too.

**Images** can be any `https://` address (Herald never fetches it), or uploaded.
Uploads must be JPEG, PNG, GIF or WebP up to 8 MB. Herald checks the file
itself rather than its name, re-saves it from its pixels at up to 1200px wide,
and stores it under a random name in `public/assets/herald`. SVG is refused.

### Latest discussions

The Latest discussions block lists **newest**, **latest activity** or
**trending this week** (the most replies in the last seven days), up to ten,
as titles only or with an excerpt, from the tags you tick (and optionally their
child tags) or from everywhere.

- **It lists the forum as a guest sees it.** Discussions in tags guests cannot
  see, hidden discussions and private discussions never appear, whoever writes
  the mailing.
- **The list is fixed when the send begins,** so everyone gets the same list
  however long the send takes.
- **Leave out discussions an earlier block lists** lets a second list (say,
  *New discussions*) skip what the first already showed.
- **An issue with nothing to list is skipped,** not sent. A weekly digest in a
  quiet week stays out of everyone's inbox. Sent by hand, Herald tells you
  instead.

Editor mailings can list discussions too: put `{latest_discussions}` on a line
of its own for the five most active, as guests see them.

### What a designed email is made of

The builder writes the HTML that email clients actually handle: layout tables, a
600px column that shrinks on phones, inline styles, buttons that keep their
colour where a client strips a link's background, images with a width and
alt text, and colours set on both text and background so a client's dark mode
never leaves dark text on a dark background. A plain-text part is always
included.

What the browser sends is never trusted: blocks are checked against a list of
known block types and fields, text is escaped, numbers are clamped, colours must
be colours, and links must be `http`, `https`, `mailto`, a path on your forum, or
start with a link quick tag such as `{suite_url}`.

## Scheduling and repeats

![Scheduling: a date, a time, a time zone and an optional repeat](screenshots/schedule.png)

In **Send…**, switch to **Schedule** and pick a date, a time and a time zone (your
browser's, to start with). Any time from five minutes to a year ahead.

- Until it goes you can **edit it**, or press **Unschedule** to make it a draft
  again.
- The Bulk mail list shows when each scheduled mailing goes next.
- The audience is counted **when it goes**, not when you scheduled it.

Choose **Repeat** daily, weekly or monthly to send it again and again:

- **Each issue is a mailing of its own,** with its own delivery record. Open the
  repeating mailing to see its **Issues**.
- **The time stays the time.** 9:00 in New York is 9:00 in New York on both
  sides of a clock change. A monthly mailing set for the 31st goes on the last
  day of shorter months and comes back to the 31st.
- **Edit the repeating mailing** to change every issue still to come.
- If your server was down when an issue was due, one issue goes as soon as it
  is back — not a backlog of them.

Scheduled mailings start from Flarum's scheduler, so cron must run
`php flarum schedule:run` every minute (see
[How sending works](#how-sending-works)).

## Templates

The **Templates** tab holds mailings you start from rather than send. Herald
creates two the first time you open it:

- **Weekly digest** — a hero, *Trending this week*, *New discussions*, a button
  and a sign-off. Made to repeat weekly.
- **Announcement** — a hero, some text, a button and a sign-off.

**Use template** makes a new draft from it. Any mailing, sent ones included,
can be **saved as a template** or **copied**. Templates you no longer use can be
**archived** (and restored).

![The Templates tab](screenshots/templates.png)

## The signup widget and guest subscribers

Herald adds a **Newsletter signup** widget to **Bespoke** and to
**FoF Forum Widgets**. Neither shows it until you place it in your widget
layout. Its title, text and button wording can be changed in **Admin → Herald**.

| Guest | Member | Dark mode |
| --- | --- | --- |
| ![The widget for a guest: an email field and Subscribe](screenshots/signup-guest.png) | ![The widget for a subscribed member](screenshots/signup-member.png) | ![The guest widget in dark mode](screenshots/signup-guest-dark.png) |

**Members** see one button. It is the same switch as *News from the forum team*
on their Settings page, so there is one answer per member, never a second list.

**Guests** enter their email address, and Herald sends a confirmation link
(double opt-in):

- **Nothing is sent to an address until its owner clicks the link.** Opening the
  link shows a Confirm button, so a mail scanner that opens every link cannot
  confirm on their behalf. The link works once, for 48 hours.
- **Addresses nobody confirms are forgotten after a week.**
- **The form says the same thing for every address,** so it cannot be used to
  find out who is subscribed or who has an account. If the address belongs to a
  member, the member is emailed a note pointing to their settings page instead.
- **It is rate-limited** (five tries an hour from one connection, three
  confirmation emails a day to one address) and has a hidden field that catches
  form-filling bots. No IP address is stored.
- **Every newsletter a guest gets has its own one-click unsubscribe link and
  headers.**

To include guests in a mailing, switch on **Newsletter subscribers** on the
Recipients tab. Only confirmed, still-subscribed guests are counted, and the
count says how many. The member filters do not apply to guests. A guest who
later registers with the same address gets one copy, as a member.

The **Newsletter subscribers** tab on the Bulk mail list shows every guest,
whether they have confirmed, and when they signed up. Search it, **export it as
CSV**, or **remove** someone along with their delivery records.

![The Newsletter subscribers tab](screenshots/subscribers.png)

Guests are off in **Admin → Herald → Let guests subscribe** if you only want
members on your list. The widget then shows to members only.

## Delivery records

Every mailing keeps a record of what happened to each copy. Open a sent mailing
and choose **Deliveries**:

![The Deliveries tab: every recipient and what happened to their copy](screenshots/deliveries.png)

| Outcome | Meaning |
| --- | --- |
| **Sent** | Handed to your mail server. |
| **Failed** | Your mail server refused it. Retried automatically after 5 minutes, then after 30. The reason is shown. |
| **Skipped** | They opted out after you pressed Send, so it was not sent. Consent is checked again just before each copy goes. |
| **Unknown** | The server stopped while handing it over, so it may have arrived. These are never resent automatically. |

- **Nobody is sent the same mailing twice by accident.** Each copy's record is
  written before the copy is handed over, and can be written only once.
- **Resend to failed** appears once a mailing has finished and some copies
  finally failed.
- The Bulk mail list shows failed, skipped and unknown counts under each
  mailing.

## Quick tags

Tags are written in braces. They work in the **subject** and the **message**,
including inside link addresses: `[your profile]({suite_url}/u/{member_username})`
becomes a working link to each member's own profile.

The names are Invision's, so an admin moving from IPS types what they already
know.

### Member tags

| Tag | Becomes |
| --- | --- |
| `{member_id}` | The member's ID number |
| `{member_name}` | Their display name |
| `{member_username}` | Their username |
| `{member_joined}` | The date they joined, in their own language (`2. Oktober 2026` for a German member) |
| `{member_last_visit}` | The date of their last visit |
| `{member_posts}` | How many posts they have made |
| `{member_discussions}` | How many discussions they have started |

### Forum tags

| Tag | Becomes |
| --- | --- |
| `{suite_name}` | The forum's title |
| `{suite_url}` | The forum's address |
| `{reg_total}` | How many members the forum has |
| `{total_posts}` | How many posts the forum has |
| `{total_discussions}` | How many discussions the forum has |

### Link tags

| Tag | Becomes |
| --- | --- |
| `{unsubscribe_url}` | Their personal one-click unsubscribe link |
| `{settings_url}` | Their account settings page |

Every email already ends with both links in its footer, so you only need these
if you want them in the body as well.

### Aliases

These friendlier names also work:

| Alias | Same as |
| --- | --- |
| `{username}` | `{member_name}` |
| `{board_title}`, `{board_name}` | `{suite_name}` |
| `{board_url}` | `{suite_url}` |

Invision also has "most users ever online" tags. Flarum keeps no such record, so
Herald leaves them out rather than ship tags that always print zero.

Values are escaped for where they appear. A member whose display name contains
`<` or `&` gets it shown as text, never as markup. A tag that is not recognised
is left exactly as written.

## Choosing recipients

![The Recipients tab: newsletter subscribers, then group, join date, last visit, post and discussion filters, with a live count](screenshots/recipients.png)

Every filter you set narrows the list further. Filters left empty do nothing.

| Filter | What it does |
| --- | --- |
| **Groups: send to** | Members of *any* of the ticked groups. Tick none to send to everyone. Ticking **Members** also means everyone. |
| **Groups: leave out** | Excludes anyone in a ticked group. |
| **Joined** | On or after, and/or on or before, a date. |
| **Last visit** | *Visited within the last N days* (your active members) or *has not visited for at least N days* (the "we've missed you" mailing). Members who have never visited count as not having visited. |
| **Posts** | At least and/or at most N posts. |
| **Discussions** | At least and/or at most N discussions started. |
| **Suspended members** | Left out unless you switch this on. Only shown when flarum/suspend is enabled. |

Two rules apply to every mailing and **cannot be switched off**:

- **Members who have opted out are never sent bulk mail.**
- **Members who have not confirmed their email address are never sent bulk
  mail.**

The count shows how many members each rule excluded, so the number is never a
surprise.

A send also includes only members who existed when you pressed **Send**.
Someone who registers halfway through a large send is not added to it.

## Consent and unsubscribing

**Members are opted in by default,** as in Invision Community, and every email
says plainly how to opt out.

Herald keeps its own consent flag. Agreeing to hear about replies to your
discussions is not the same as agreeing to a mailing list, so turning off
Herald's emails does not affect a member's notification settings, and the
reverse is true too.

Members can opt out in three ways:

1. **The Unsubscribe link** at the foot of every email. No sign-in is needed.
   The link opens a page that asks first, so mail scanners that open every link
   in a message do not unsubscribe anyone by accident.
2. **One-click unsubscribe in their mail app.** Every email carries
   `List-Unsubscribe` and `List-Unsubscribe-Post` headers
   ([RFC 8058](https://www.rfc-editor.org/rfc/rfc8058)). Gmail, Yahoo and
   Apple Mail show their own Unsubscribe button. Since 2024, Gmail and Yahoo
   expect these headers from bulk senders and may send mail without them to
   spam.
3. **Their Settings page**, under *News from the forum team*. This is also where
   to opt back in.

![The switch on a member's Settings page](screenshots/settings.png)

The unsubscribe page uses your forum's title and brand colour, works in light
and dark mode, and offers a **Resubscribe** button in case someone clicked by
mistake.

![The unsubscribe page](screenshots/unsubscribe.png)

Unsubscribe links are signed with a secret generated on your forum. A link
stops working if the member changes their email address, so an old email cannot
change the preferences of an address it was never sent to.

## How sending works

An email cannot be recalled, and sending thousands of them inside one web
request is how a send dies halfway with no record of who got it. So Herald
sends **in batches**, recording its progress after every single email.

Three things can push a send along, and Herald uses whichever your forum has:

| Driver | When it runs |
| --- | --- |
| **The progress page** | Whenever the page is open in an admin's browser. It sends one batch per request, like Invision's progress screen. This works on any host, with nothing to set up. |
| **The queue** | If your forum runs a queue worker (Redis, database, Horizon). The send continues after you close the page. |
| **The scheduler** | If cron runs Flarum's scheduler. Herald sends batches every minute until it's done. |

All three can run at once without harm. Each batch is taken with a lock, so two
of them never send the same batch or mail the same member twice.

If neither a queue nor cron is set up, the progress page tells you to keep it
open. To let sends carry on without it, add Flarum's scheduler to cron. This is
worth doing for Flarum in general:

```
* * * * * cd /path/to/flarum && php flarum schedule:run >> /dev/null 2>&1
```

You can also push a send along by hand:

```bash
php flarum herald:process
```

**Cancel sending** stops at the next member, not the next batch. Members who
already received it keep their copy, and the mailing is marked *Cancelled* with
the count it reached.

A failed email (a rejected address, a provider hiccup) is recorded with the
reason and retried later; it does not stop the rest of the send. See
[Delivery records](#delivery-records).

The scheduler also does three other jobs every minute: it starts mailings whose
time has come, retries failed copies whose time has come, and picks up any send
whose queue job was lost.

### Sending limits

If your mail provider limits how many emails you may send per minute, hour or
day, enter those numbers in **Admin → Herald**. The limits are shared by every
mailing, so two mailings sending at once still stay under them. A send that
reaches a limit pauses, says so on its progress page, and carries on by itself
when there is room again.

## Works with your editor

Herald does not have an editor of its own. It uses your forum's editor:

| Your forum runs | You write mailings in |
| --- | --- |
| **flarum/markdown** | The Markdown editor and its formatting toolbar |
| **[FoF Rich Text](https://github.com/FriendsOfFlarum/rich-text)** | The rich text editor |
| **[Scribe](https://github.com/ernestdefoe/scribe)** | Scribe's WYSIWYG editor and toolbar |

Mentions, emoji, and upload buttons from extensions such as FoF Upload all work
as they do in the composer.

**Why the mailing screen is on the forum, not in the admin panel.** Rich editors
install themselves into the forum's interface only, and the admin panel never
loads them. A compose screen in the admin panel would quietly give every forum a
plain text box. Herald's screens live at `/herald` on the forum, behind their
own permission.

Mailings are stored the way posts are, so **switching editors later is safe**.
A mailing written in Markdown still previews and sends correctly after you move
to Scribe or Rich Text.

## Settings

**Admin → Herald**

| Setting | Default | What it does |
| --- | --- | --- |
| **Emails per batch** | 50 | How many emails each batch sends. Lower it if your mail provider limits how fast you can send. |
| **Seconds between batches** | 0 | A pause between batches, for providers with an hourly cap. For example, 50 per batch every 60 seconds is 3,000 an hour. |
| **Reply-to address** | *(empty)* | Where replies to a mailing go. Empty uses your forum's normal sending address. |
| **Most emails a minute / an hour / a day** | *(no limit)* | Your mail provider's sending limits, shared by every mailing. See [Sending limits](#sending-limits). |
| **Let guests subscribe** | On | Whether the signup widget offers guests the email form. |
| **Widget title / text / button text** | *(translated defaults)* | The signup widget's wording. |

The sender name and address are your forum's own, set in **Admin → Email**.

## Permissions

**Admin → Permissions → Moderate → Send bulk email with Herald.**

Administrators always have it. Give it to another group only if you trust them
to email your whole membership. Members without it don't see the menu item, and
the API refuses them.

## What the email looks like

Herald wraps your message in Flarum's own email layout, with your forum's logo
at the top, so it looks like every other email your forum sends. On top of
that, Herald:

- **makes every link absolute,** so mentions, uploads and internal links work
  from an inbox;
- **turns embeds into links,** because email clients strip video players and
  iframes;
- **removes scripts and event handlers;**
- **inlines its styles,** because several mail clients drop `<style>` blocks;
- **caps the logo's size,** so a wide logo cannot push the email off-screen;
- **adds a plain-text part,** with links written out as `text (address)`;
- **translates the footer into each member's own language.** The message is
  yours, in whatever language you wrote it.

If your forum is set to send plain-text or HTML-only email, Herald follows that
setting.

## For developers: adding tags and filters

Herald has the same extension points as Invision's BulkMail and MemberFilter.
Other extensions can add **quick tags** and **recipient filters**.

Register them with Herald's extender. Wrap it in `Conditional` so your extension
still boots on forums without Herald:

```php
use Flarum\Extend;

return [
    (new Extend\Conditional())
        ->whenExtensionEnabled('ernestdefoe-herald', fn () => [
            (new \ErnestDefoe\Herald\Extend\Herald())
                ->tags(PickemTags::class)
                ->filter(PickemPlayersFilter::class),
        ]),
];
```

### A tag provider

```php
use ErnestDefoe\Herald\Tag\TagProvider;
use Illuminate\Support\Collection;

class PickemTags implements TagProvider
{
    public function tags(): array
    {
        // name (no braces) => translation key for its description
        return ['pickem_rank' => 'acme-pickem.herald.tag_rank'];
    }

    public function urlTags(): array
    {
        // Tags whose value is a URL, so they work inside a link's address.
        return [];
    }

    public function values(Collection $users): array
    {
        // 🚨 You are handed a whole BATCH of members. Query once for all of
        // them, not once per member.
        $ranks = Rank::query()->whereIn('user_id', $users->pluck('id'))->pluck('rank', 'user_id');

        return $users->mapWithKeys(fn ($user) => [
            $user->id => ['pickem_rank' => (string) ($ranks[$user->id] ?? '–')],
        ])->all();
    }
}
```

Return **plain text**. Herald escapes values for wherever the tag appears.

### A recipient filter

```php
use ErnestDefoe\Herald\Filter\Filter;
use Illuminate\Database\Eloquent\Builder;

class PickemPlayersFilter implements Filter
{
    public function key(): string
    {
        return 'pickem';
    }

    public function apply(Builder $query, array $config): void
    {
        if (empty($config['playersOnly'])) {
            return; // nothing set: leave the query alone
        }

        // 🚨 Qualify every column. Another filter may have joined a table
        // with a column of the same name.
        $query->whereExists(fn ($q) => $q->selectRaw('1')
            ->from('pickem_entries')
            ->whereColumn('pickem_entries.user_id', 'users.id'));
    }
}
```

A filter's settings are saved in the mailing under its `key()`.

### Its controls on the Recipients tab

The Recipients tab is an `ItemList`, so you can add your controls next to the
built-in ones. Write into `this.section('<your key>')` and call
`this.attrs.onchange()`, which saves the change and refreshes the count:

```js
import { extend } from 'flarum/common/extend';
import Switch from 'flarum/common/components/Switch';
import RecipientFilters from 'ext:ernestdefoe/herald/forum/components/RecipientFilters';

extend(RecipientFilters.prototype, 'items', function (items) {
  const config = this.section('pickem');

  items.add(
    'pickem',
    <fieldset className="HeraldFilter">
      <legend>Pick'em</legend>
      <Switch
        state={!!config.playersOnly}
        onchange={(value) => {
          config.playersOnly = value;
          this.attrs.onchange();
        }}
      >
        Only members who have made picks
      </Switch>
    </fieldset>,
    55
  );
});
```

Add `useExtensions: ['ernestdefoe/herald']` to your `webpack.config.js`, and
list Herald under `optional-dependencies` in `composer.json`, so the bundle
loads in the right order.

## Troubleshooting

**The progress bar does not move after I close the page.**
Your forum has neither a queue worker nor cron running the scheduler. Reopen the
mailing and the page will carry on sending, or set up cron as shown in
[How sending works](#how-sending-works).

**Every email in a mailing failed.**
Herald counts the failures and logs the reason for each in
`storage/logs/flarum-<date>.log`, under `[herald]`. Most often the forum's mail
settings are wrong. Send yourself a test from **Admin → Email**.

**The count is lower than my number of members.**
Opted-out and unconfirmed members are never included, and suspended members are
left out unless you include them. The count shows how many were left out for
each reason.

**A scheduled mailing did not go out.**
Scheduled and repeating mailings are started by Flarum's scheduler. Check that
cron runs `php flarum schedule:run` every minute.

**A repeating digest skipped a week.**
Its Latest discussions blocks had nothing to list, so the issue was skipped
rather than sent empty. It shows as *Skipped* in the repeating mailing's Issues.

**Guests say they never got the confirmation email.**
It comes from your forum's normal sending address; ask them to check spam.
Addresses that are never confirmed are never mailed and are removed after a week.

**A tag came through as text, such as `{member_nmae}`.**
It is misspelled, so Herald left it alone. Click tags in from the Quick Tags
panel to avoid typos.

**Links to my own forum show "nofollow".**
That comes from the forum's link handling for posts and has no effect in an
email.

## Requirements

- Flarum **2.0** or later
- PHP **8.3** or later
- A working mail configuration in **Admin → Email**

## Support

- **Support forum:** [Herald on ernestdefoe.online](https://ernestdefoe.online/d/103)
- **Flarum community:** [Herald on discuss.flarum.org](https://discuss.flarum.org/d/39967-herald-built-using-ai)
- **Bug reports:** [GitHub issues](https://github.com/ernestdefoe/herald/issues)

## Licence

MIT. See [LICENSE](LICENSE).

Herald is free. If it saves you an afternoon, a star on GitHub helps other
forum owners find it.
