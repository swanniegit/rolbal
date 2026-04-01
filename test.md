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

## Challenge System

### Database Setup
- [ ] Run `sql/add_challenges.sql`
- [ ] Verify `challenges` table has sample data
- [ ] Verify `challenge_sequences` table populated
- [ ] Verify `challenge_attempts` table exists

### Challenge List
- [ ] Navigate to `/challenges/index.php`
- [ ] Verify sample challenges display (Full Routine, Quick Draw, etc.)
- [ ] Verify difficulty badges show (beginner/intermediate/advanced)
- [ ] Verify bowl count and sequence count display
- [ ] Anonymous users see "Login to Play" prompt
- [ ] Logged-in users can click to start

### Playing a Challenge
- [ ] Click a challenge card to start
- [ ] Verify start prompt shows challenge overview
- [ ] Click "Start Challenge"
- [ ] Verify sequence info displays (end length, delivery)
- [ ] Verify delivery indicator shows Forehand/Backhand
- [ ] Record a bowl position
- [ ] Verify score popup shows points earned
- [ ] Verify total score updates
- [ ] Verify progress bar advances
- [ ] Test Toucher button (adds +5 bonus)
- [ ] Test Undo button
- [ ] Test miss zone buttons (Too Long, Too Short, Too Far Left/Right)
- [ ] Complete all sequences
- [ ] Verify auto-redirect to results page

### Challenge Results
- [ ] Verify total score and percentage display
- [ ] Verify "New Personal Best" badge when applicable
- [ ] Verify score breakdown by sequence
- [ ] Verify previous attempts history shows
- [ ] Click previous attempt to view its breakdown
- [ ] Click "Play Again" to start new attempt

### Challenge Progress Persistence
- [ ] Start a challenge, record some bowls
- [ ] Click "Quit" or navigate away
- [ ] Return to challenges list
- [ ] Verify "In Progress (X/Y)" badge shows
- [ ] Click to resume challenge
- [ ] Verify progress is preserved

### Challenge History
- [ ] Complete multiple attempts of same challenge
- [ ] View results page
- [ ] Verify all completed attempts listed
- [ ] Verify best score highlighted
- [ ] Click "Best" badge on challenge list
- [ ] Verify links to results page
