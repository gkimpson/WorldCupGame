# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: predictions-flags-check.spec.js >> prediction rows keep flags and score controls aligned
- Location: ../../../../private/tmp/predictions-flags-check.spec.js:3:1

# Error details

```
Error: locator.fill: Error: strict mode violation: getByLabel('Password') resolved to 2 elements:
    1) <input type="password" name="password" label="Password" required="required" data-flux-control="" placeholder="Password" data-flux-group-target="" autocomplete="current-password" aria-labelledby="lofi-label-1636878c4f221" class="w-full border rounded-lg block disabled:shadow-none dark:shadow-none appearance-none text-base sm:text-sm py-2 h-10 leading-[1.375rem] ps-3 pe-10 bg-white dark:bg-white/10 dark:disabled:bg-white/[7%] text-zinc-700 disabled:text-zinc-500 placeholder-zinc-400 disabled:placeholde…/> aka getByRole('textbox', { name: 'Password' })
    2) <button type="button" x-on:click="toggle()" x-data="fluxInputViewable" x-bind:data-viewable-open="open" data-flux-button="data-flux-button" aria-label="Toggle password visibility" class="relative items-center font-medium justify-center gap-2 whitespace-nowrap disabled:opacity-75 dark:disabled:opacity-75 disabled:cursor-default disabled:pointer-events-none justify-center h-8 text-sm rounded-md w-8 inline-flex -ms-1.5 -me-1.5 bg-transparent hover:bg-zinc-800/5 dark:hover:bg-white/15 text-zinc-500 hover:…>…</button> aka getByRole('button', { name: 'Toggle password visibility' })

Call log:
  - waiting for getByLabel('Password')

```

# Page snapshot

```yaml
- generic [ref=e1]:
  - generic [ref=e3]:
    - link "WorldCupGame" [ref=e4] [cursor=pointer]:
      - /url: http://worldcup-104-0-0.test
      - img [ref=e6]
      - generic [ref=e8]: WorldCupGame
    - generic [ref=e10]:
      - generic [ref=e11]:
        - generic [ref=e12]: Log in to your account
        - generic [ref=e13]: Enter your email and password below to log in
      - generic [ref=e14]:
        - generic [ref=e15]:
          - generic [ref=e16]: Email address
          - textbox "Email address" [active] [ref=e18]:
            - /placeholder: email@example.com
            - text: bigz@dev.test
        - generic [ref=e19]:
          - generic [ref=e20]:
            - generic [ref=e21]: Password
            - generic [ref=e22]:
              - textbox "Password" [ref=e23]
              - button "Toggle password visibility" [ref=e25]:
                - img [ref=e26]
          - link "Forgot your password?" [ref=e29] [cursor=pointer]:
            - /url: http://worldcup-104-0-0.test/forgot-password
        - generic [ref=e30]:
          - checkbox "Remember me" [ref=e31]
          - generic [ref=e33]: Remember me
        - button "Log in" [ref=e35]:
          - img [ref=e37]
          - generic [ref=e40]: Log in
      - generic [ref=e41]:
        - text: Don't have an account?
        - link "Sign up" [ref=e42] [cursor=pointer]:
          - /url: http://worldcup-104-0-0.test/register
  - generic:
    - status
  - generic [ref=e45]:
    - generic [ref=e47]:
      - generic [ref=e48] [cursor=pointer]:
        - generic: Request
      - generic [ref=e49] [cursor=pointer]:
        - generic: Timeline
      - generic [ref=e50] [cursor=pointer]:
        - generic: Views
        - generic [ref=e51]: "7"
      - generic [ref=e52] [cursor=pointer]:
        - generic: Queries
        - generic [ref=e53]: "3"
    - generic [ref=e54]:
      - generic [ref=e62] [cursor=pointer]: GET /login
      - generic [ref=e63] [cursor=pointer]:
        - generic: 113ms
      - generic [ref=e65] [cursor=pointer]:
        - generic: 4MB
      - generic [ref=e67] [cursor=pointer]:
        - generic: 13.x
```

# Test source

```ts
  1  | const { test, expect } = require('/Users/gavin/node_modules/playwright/test');
  2  | 
  3  | test('prediction rows keep flags and score controls aligned', async ({ page }) => {
  4  |     const consoleErrors = [];
  5  | 
  6  |     page.on('console', (message) => {
  7  |         if (message.type() === 'error') {
  8  |             consoleErrors.push(message.text());
  9  |         }
  10 |     });
  11 | 
  12 |     await page.goto('http://worldcup-104-0-0.test/login');
  13 |     await page.getByLabel('Email address').fill('bigz@dev.test');
> 14 |     await page.getByLabel('Password').fill('password');
     |                                       ^ Error: locator.fill: Error: strict mode violation: getByLabel('Password') resolved to 2 elements:
  15 |     await page.getByTestId('login-button').click();
  16 |     await page.waitForURL('**/dashboard', { timeout: 10000 });
  17 | 
  18 |     await page.goto('http://worldcup-104-0-0.test/predictions');
  19 |     await expect(page.getByRole('heading', { name: 'My Predictions' })).toBeVisible();
  20 | 
  21 |     const firstFixtureRow = page.locator('[wire\\:key^="fixture-"]').first();
  22 |     await expect(firstFixtureRow).toBeVisible();
  23 |     await expect(firstFixtureRow.locator('svg')).toHaveCount(2);
  24 | 
  25 |     const rowBox = await firstFixtureRow.boundingBox();
  26 |     const scoreBox = await firstFixtureRow.locator('input').first().boundingBox();
  27 | 
  28 |     expect(rowBox).not.toBeNull();
  29 |     expect(scoreBox).not.toBeNull();
  30 |     expect(scoreBox.x).toBeGreaterThan(rowBox.x + rowBox.width * 0.35);
  31 |     expect(scoreBox.x).toBeLessThan(rowBox.x + rowBox.width * 0.6);
  32 |     expect(consoleErrors).toEqual([]);
  33 | });
  34 | 
```