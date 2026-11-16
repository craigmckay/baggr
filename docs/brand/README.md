# baggr brand files

Chosen direction: **A, "Peak"**: a solid peak with a lower shoulder, with nothing on the summit. Source designs are on the design canvas "baggr logo and icon options". See SPEC.md §1, "Look and feel".

| File | Use | Goes to (in the app) |
| --- | --- | --- |
| `baggr-mark.svg` | Hill mark, full detail, for 64px and up. Its drawing area is cropped so the foot of the mountain is the bottom edge. | `x-app-logo-icon` (inline the path, `fill="currentColor"`) |
| `baggr-mark-small.svg` | Simplified mark for 16–48px | Source for the favicons |
| `baggr-lockup-light.svg` / `baggr-lockup-dark.svg` | Mark + wordmark, for reference | Sidebar brand and landing page (build with real text, not this SVG) |
| `favicon.svg` | Browser tab icon | `public/favicon.svg` |
| `favicon.ico` | 16, 32 and 48px | `public/favicon.ico` |
| `apple-touch-icon.png` | 180px, iPhone home screen | `public/apple-touch-icon.png` |
| `icon-192.png`, `icon-512.png` | Android / add to home screen | `public/icons/` + web manifest |
| `icon-512-maskable.png` | Android adaptive icon (mark inside the safe zone) | `public/icons/` + manifest, `"purpose": "maskable"` |
| `app-icon.svg` | 512px rounded icon, for previews | Not shipped |
| `landing.html` | Standalone mock-up of the signed-out landing page | Reference for the Blade view |

**Colours** (provisional until open question 7 is settled): hill green `#1F4D3A`, heather (on dark) `#C9B5DD`, ink `#14211B`, landing background `#13281F`, dark panel `#0F1D17`.

**Lock-up rule:** the foot of the mountain sits on the wordmark's **baseline**, and the mark is 0.75 × the font size tall, so the peak reaches about the top of the "b". In CSS: `display: flex; align-items: baseline` with the cropped SVG.

**Font:** Nunito 900 (Google Fonts) for the wordmark. The lock-up SVGs load Nunito from Google Fonts and fall back to a system font, so in the app the wordmark should be real text styled with Nunito, not an image.

**Tagline:** "Get yourselves together." (brand tagline), with "The hills are calling." as the supporting line.
