# Rolbal Test Checklist

## Database Setup
- [ ] Run `sql/schema.sql`
- [ ] Run `sql/add_players.sql`
- [ ] Verify `players` table exists
- [ ] Verify `sessions` has `player_id` and `is_public` columns

## Registration Flow
- [ ] Navigate to `/register.php`
- [ ] Fill form with valid email, password (8+ chars), name
- [ ] Select hand preference (L/R)
- [ ] Solve math captcha
- [ ] Submit form
- [ ] Verify redirect to `/verify.php?registered=1`
- [ ] Click verification link
- [ ] Verify account is activated

## Login Flow
- [ ] Navigate to `/login.php`
- [ ] Enter credentials
- [ ] Test password show/hide toggle
- [ ] Submit form
- [ ] Verify redirect to `/players.php`
- [ ] Verify name shows in header on index

## Logout Flow
- [ ] Click logout on players page
- [ ] Verify redirect to index
- [ ] Verify "Login" link appears in header

## Anonymous Play Limit
- [ ] Logout (or use incognito)
- [ ] Navigate to `/game.php`
- [ ] Verify "3 free games remaining" notice
- [ ] Start a game
- [ ] Verify counter decrements
- [ ] Start 2 more games
- [ ] Verify limit prompt appears on 4th attempt
- [ ] Verify register/login buttons work

## Session Visibility
- [ ] Login as user
- [ ] Create a new session
- [ ] Go to `/history.php`
- [ ] Click eye icon to toggle visibility
- [ ] Verify icon changes (open/closed eye)
- [ ] Logout and check session visibility on history (should be hidden if private)

## Player Profile
- [ ] Login and go to `/players.php`
- [ ] Verify profile card shows name, hand, stats
- [ ] Verify session list shows
- [ ] Verify visibility toggle works on sessions

## Error Handling
- [ ] Try registering with existing email
- [ ] Try login with wrong password
- [ ] Try login before email verification
- [ ] Try invalid captcha answer

## Security - Session Authorization
- [ ] Login as User A, create a session, note the ID
- [ ] Login as User B, try to delete User A's session via API
- [ ] Verify deletion is rejected ("Cannot delete this session")
- [ ] Login as User A, delete own session
- [ ] Verify deletion succeeds
- [ ] As anonymous user, create session
- [ ] As logged-in user, try to delete anonymous session
- [ ] Verify deletion is rejected

## Mobile/PWA
- [ ] Test on mobile viewport
- [ ] Verify touch targets are adequate
- [ ] Test PWA install prompt
