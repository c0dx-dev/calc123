# Calc123 — WordPress Formula Calculator (v1.3)

A lightweight, secure and flexible formula-based calculator plugin for WordPress.  
Create multiple calculators in the admin, define variables (number / select / hidden), enter a formula in familiar math notation, and embed each calculator with a shortcode.

---

## Key features
- **Version:** `1.3`  
- Create / Edit / Duplicate / Delete multiple calculators in admin  
- Formula support: `+ - * / ^` (power), parentheses, comparisons: `> < >= <= == !=`  
- Functions: `IF(cond, a, b)`, `MAX(a,b)`, `MIN(a,b)`, `ROUND(a,decimals)`  
- Variable types: `number`, `select` (Label:Value list), and hidden variables  
- Per-calculator currency/text display (before / after result)  
- Optional simple math captcha (server-checked) — auto-refreshes after each successful calculation  
- Client + server validation (required fields, numeric checks)  
- AJAX calculation (no page reload), secure with nonces  
- Safe formula parsing (tokenize → RPN → evaluate) — no `eval()`  
- Minimal built-in styles; easily override with theme/CSS

---

## Quick install

The plugin now ships as a small folder with assets. To install:

### Manual (FTP / file manager)
1. Create folder `calc123` on your server:  
   `/wp-content/plugins/calc123/`
2. Upload these files into that folder:
   - `calc123.php` (main plugin file)  
   - `calc123-frontend.js` (frontend JS, enqueued by the plugin)  
   - `calc123-frontend.css` (frontend CSS, enqueued by the plugin)  
   - `about.html` (optional informational file shown in plugin details)  
   - `.htaccess` (optional protection / rules — keep as provided)
3. Set file permissions (typical):
   - Folder: `755`
   - Files: `644`
4. In WP admin go to **Plugins → Installed Plugins** and click **Activate** for **Calc123**.
5. Go to **Calc123** in the admin menu to create calculators and copy the shortcode into pages.

### Install from zip (WP-Admin or WP-CLI)
- Zip the `calc123` folder (it must contain the five files above at the root of the archive).  
- Upload via **Plugins → Add New → Upload Plugin** and activate.

---

## Usage (short)
1. **Create a calculator** in admin:
   - Set **Name**
   - Enter a **Formula** (e.g. `IF(distance > 100, 1000 + weight * 50, 500 + weight * 30)`)
   - Add **Variables**:
     - `name` (e.g. `distance`) — internal variable name (latin letters/digits/underscore)
     - `label` — visible label shown to user
     - `type` — `number` or `select`
     - `options` — for `select`, format: `Label1:Value1,Label2:Value2`
     - `hide` — optional checkbox to hide the field (value still used in calc)
   - Optional: set **currency display** text and position, enable **captcha**
2. **Embed** in a page/post:  
   `[calc123 id="N"]` 
   (Place the phrase above where you would insert the shortcode. If `id` is omitted in the admin UI, the first calculator will be used as a default.)

---

## Formula rules (plain language)
- **Variable names:** use Latin letters, digits and underscores only. Examples: `a`, `price`, `num_items`, `x1`. Names must match exactly in the formula and variable list.
- **Operators:** `+`, `-`, `*`, `/`, `^` (power).
- **Parentheses:** use `(` and `)` to group and control order.
- **Comparisons:** `>`, `<`, `>=`, `<=`, `==`, `!=`. They return `1` if true, `0` if false.
- **Functions available:**
  - `IF(cond, a, b)` — returns `a` if `cond` is true (non-zero), else `b`.
  - `MAX(a, b)`, `MIN(a, b)` — maximum / minimum of two values.
  - `ROUND(a, decimals)` — round `a` to `decimals` places.
- **Examples of valid formulas:**
  - `a + b`
  - `a + b * (c ^ 2)`
  - `IF(weight > 10, 100 + weight * 5, 50 + weight * 3)`
  - `ROUND(price * (1 - discount/100), 2)`

---

## Examples (practical)
### 1) Simple sum
- Formula: `a + b`  
- Variables: `a` (number), `b` (number)  
- Input: `a=24`, `b=12` → Result **36**

### 2) Delivery cost (conditional)
- Formula: `IF(distance > 100, 1000 + weight * 50, 500 + weight * 30)`  
- Variables: `distance` (number), `weight` (number)  
- Examples:
  - `distance=120`, `weight=5` → `1000 + 5*50 = 1250`
  - `distance=80`, `weight=5` → `500 + 5*30 = 650`

### 3) Package + pages (select)
- Formula: `package + pages * per_page`  
- `package` (select): `Standard:10000,Premium:20000`  
- `pages` (number), `per_page` (number)  
- Example: `package=Premium`, `pages=5`, `per_page=1500` → `20000 + 5*1500 = 27500`

### 4) Geometry & rounding
- Area of circle: `3.14159 * r ^ 2`  
- Round price: `ROUND(price * (1 - discount/100), 2)`

---

## Frontend & validation
- Visible fields are rendered `required`.  
- Client-side numeric checks improve UX; server-side validation enforces correctness.  
- If JavaScript is disabled, server still validates and returns errors.  
- Error messages: required-field warnings, numeric-format errors, division-by-zero, captcha failures, and formula parse/evaluate errors.

---

## Captcha
- Optional per-calculator simple math captcha (two small ints to sum).  
- Captcha protected by server token, validated on server.  
- Captcha **refreshes automatically after every successful calculation**.

---

## Developer notes
- **AJAX actions**
  - `wp_ajax_calc123_compute` — compute result
  - `wp_ajax_calc123_new_captcha` — request new captcha (refresh)
- Nonces are used for AJAX and admin actions (`wp_verify_nonce` enforced).
- Frontend classes: `.calc123-frontend-form`, `.calc123-result` — override CSS if needed.
- The evaluator is implemented without `eval()`:
  - Tokenizer → Shunting-yard to RPN → Stack evaluator (limited safe functions).
- Admin operations (duplicate/delete/save) are protected with nonces and safe redirects.

---

## Changelog
**v1.3**
- Buffered output + safe redirect + JS fallback to fix white-page on duplicate/delete.
- Delegated admin-side deletion of variable rows (works for dynamic rows).
- Per-calculator currency display (text, before/after).
- Optional math captcha implemented and refreshed after each successful calculation.
- Stronger client/server validation and clearer error messages.

---

## Compatibility
Tested on WordPress 6.x and PHP 8+. Always test in a staging environment before production.

---

## License
MIT — use, modify and distribute. (Add your preferred license file if different.)

---

## Support & Contributing
- Open an issue or PR on the repo for bugs, suggestions, or patches.  

---
