# Dietician Progress Tracker

WordPress plugin for My Healthy Living. A logged-in member records weight and measurements. A dietician assigns one published meal plan, and the member sees that plan on the site.

The plugin file is `dietician-progress-tracker/dietician-progress-tracker.php` (version 1.3.5). Every shortcode requires a logged-in user. Every admin screen requires `manage_options`. The Assign Meal Plan user list includes the `subscriber`, `pmpro_member`, and `administrator` roles. The plugin does not create accounts or Paid Memberships Pro levels.

## Shortcodes

| Shortcode | Output |
|---|---|
| `[user_progress_form]` | Progress form. Saves one row for the current user. |
| `[user_progress_current]` | Latest row as a table, including hip, chest, arms, and thighs. |
| `[user_progress_chart]` | Four line charts: weight, waist, body fat, and BMI. |
| `[current_meal_plan]` | Title and content of the member's published meal plan. |

Logged-out visitors get a one-line message and no data.

## Where progress is stored

Activation runs `dbDelta` and creates `{prefix}user_progress`:

`id`, `user_id`, `date_logged`, `weight`, `waist`, `bmi`, `body_fat`, `calories`, `hip_cm`, `chest_cm`, `left_bicep_cm`, `right_bicep_cm`, `left_thigh_cm`, `right_thigh_cm`, `notes`.

The forms do not write `calories`. Height is user meta `height`, in centimetres. The front form sends it as a hidden read-only field named `height_cm`. It is not a column on the progress table.

The front form requires a nonce (`dpt_progress_nonce`, action `dpt_save_progress`). A failed check prints "Your session expired. Please reload the page and try again." and does not insert a row. The insert always uses `get_current_user_id()`. Empty measurements are stored as `0`. Notes go through `sanitize_textarea_field`. `date_logged` is `current_time('mysql')`.

## BMI

`dpt_resolve_logged_bmi()` runs on the front form only.

- A blank, zero, or non-numeric BMI is treated as missing.
- When weight and height are both greater than zero, the stored BMI is `weight / (height in metres)²`.
- A non-zero posted BMI is stored as entered.
- A missing height stores `0`.

`js/dpt-bmi-auto.js` fills the field with id `bmi` from `#weight` and `#height_cm`, to one decimal place. That script is loaded on the front of the site, and in wp-admin only on screens whose hook contains `dpt-user-progress` or `dpt-admin-progress`. The member form and the admin "Add New Progress Entry" form use those ids, so the browser fills BMI before submit.

Admin add and admin edit save the posted BMI with `dpt_posted_float()` and do not recalculate it. The edit row does not use those element ids, so the browser script does not change an existing entry. An admin add submitted with an empty BMI field, and without the script running, stores `0`.

## Meal plans

`meal_plan` is an admin-only post type (`public` is false, `show_ui` is true) with title and editor.

Assignment writes the post ID to user meta `meal_plan`. The assign screen, the single-user screen, and the bulk action on All Progress accept a published `meal_plan` only.

`meal_plan_id` is the key written by Assign Meal Plan before 1.3.3. `dpt_get_user_meal_plan_id()`:

1. Uses `meal_plan` when that value is a positive integer. A later unpublished plan still wins, and the shortcode then prints "Meal plan not found."
2. Otherwise, if `meal_plan_id` points at a published meal plan, copies that ID onto `meal_plan` and returns it.
3. Ignores a legacy ID that is not a published meal plan, and does not copy it.

Opening All Progress or `[current_meal_plan]` can perform that copy. The shortcode prints the title and `wp_kses_post( wpautop( content ) )`.

## Admin

**Dietician Tracker** (dashicon heart):

- **All Progress** lists every user from `get_users()`, the current meal plan, and the latest `date_logged`. Checkboxes plus a meal-plan select write `meal_plan` in bulk. The user name links to the hidden User Progress screen: `admin.php?page=dpt-user-progress&user_id=`.
- **Assign Meal Plan** assigns one published plan to one subscriber, `pmpro_member`, or administrator.
- **User Progress** lists that user's rows, edits a row in place, deletes a row only when both the entry id and `user_id` match, changes the meal plan, and adds a row. A successful meal-plan change redirects with `meal_plan_updated=1`.

The add-entry date must be `Y-m-d`. The time on that date is the current clock time. Any other date string is replaced with `current_time('mysql')`.

Edit fields sit in a table row, outside the `<form>`, because a form cannot wrap table cells. Each input uses the HTML `form` attribute so Save, the nonce, and the fields submit together.

## Charts

`[user_progress_chart]` prints a Chart.js 4.4.6 script tag from jsDelivr and one canvas per metric. Charted series are weight (kg), waist (cm), body fat (%), and BMI, ordered oldest first. Hip, chest, bicep, and thigh values stay on the current-entry table. Labels use `M d`. Canvas ids include the metric and the user id.

## Checks

```bash
php dietician-progress-tracker/tests/progress-fixes-test.php
```

The test does not boot WordPress. It checks blank and zero BMI calculation, an explicit BMI kept as entered, and legacy `meal_plan_id` reads.

## Constraints

- Direct access to the plugin file exits.
- Front-end writes need a valid nonce and a logged-in user.
- Admin writes need `manage_options` and the nonce for that action (save, add, edit, delete, or assign).
- Notes are escaped on display. Meal-plan HTML is limited to `wp_kses_post`.
- All Progress calls `get_users()` with no limit, so the screen loads every user.
