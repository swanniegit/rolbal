# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

BowlsTracker is a mobile-first PWA for tracking lawn bowls practice sessions and club competitions. Players record roll positions on a visual grid, analyze performance statistics, participate in challenges, and track live match scores.

**Live Site:** https://bowlstracker.co.za

## Production Deployment (PSCP)

**Credentials are in `.env`** (never commit `.env`):
```
DEPLOY_HOST, DEPLOY_PORT, DEPLOY_USER, DEPLOY_PASS, DEPLOY_PATH
```

**Deploy single file:**
```bash
source .env && "/c/Program Files/PuTTY/pscp.exe" -P $DEPLOY_PORT -pw "$DEPLOY_PASS" -batch "path/to/file" "$DEPLOY_USER@$DEPLOY_HOST:$DEPLOY_PATH/path/to/file"
```

**Examples:**
```bash
source .env

# Deploy a CSS file
"/c/Program Files/PuTTY/pscp.exe" -P $DEPLOY_PORT -pw "$DEPLOY_PASS" -batch "css/pages/challenge-play.css" "$DEPLOY_USER@$DEPLOY_HOST:${DEPLOY_PATH}css/pages/challenge-play.css"

# Deploy a JS file
"/c/Program Files/PuTTY/pscp.exe" -P $DEPLOY_PORT -pw "$DEPLOY_PASS" -batch "js/challenge.js" "$DEPLOY_USER@$DEPLOY_HOST:${DEPLOY_PATH}js/challenge.js"

# Deploy a directory (use -r flag)
"/c/Program Files/PuTTY/pscp.exe" -r -P $DEPLOY_PORT -pw "$DEPLOY_PASS" -batch "css/" "$DEPLOY_USER@$DEPLOY_HOST:${DEPLOY_PATH}css/"
```

**Note:** The `-batch` flag is required to skip host key confirmation prompts.

## Tech Stack

- **Backend:** PHP 8+ (no framework), PDO for database
- **Database:** MySQL/MariaDB
- **Frontend:** Vanilla JavaScript, CSS3 with CSS variables
- **Server:** XAMPP (Apache) at `http://localhost/rolbal/`

## Architecture

### API Pattern

All APIs use `ApiResponse` for consistent JSON responses:
```php
require_once __DIR__ . '/../includes/ApiResponse.php';
ApiResponse::success(['id' => $id]);       // 200, {"success": true, "id": ...}
ApiResponse::error('Message', 400);         // 400, {"success": false, "error": "..."}
ApiResponse::unauthorized();                // 401
ApiResponse::forbidden();                   // 403
ApiResponse::notFound();                    // 404
```

### Models (includes/*.php)

Static methods with PDO prepared statements. All database access goes through `Database::getInstance()` singleton:
```php
$db = Database::getInstance();
$stmt = $db->prepare('SELECT * FROM table WHERE id = :id');
$stmt->execute(['id' => $id]);
```

### Authentication

`Auth` class provides session management, CSRF tokens, and flash messages:
```php
Auth::check()          // Returns bool
Auth::id()             // Returns player_id or null
Auth::user()           // Returns full player record
Auth::generateCsrfToken() / Auth::validateCsrfToken($token)
Auth::flash('success', 'Message') / Auth::getFlash()
```

### Domain Constants (includes/constants.php)

Bowl recording uses numeric codes:
- **Hand:** L/R
- **Delivery:** 13=Backhand, 14=Forehand
- **End Length:** 9=Long, 10=Middle, 11=Short
- **Result Position:** 1-8,12 (grid positions like Short Left, Centre, Long Right)
- **Miss Position:** 20-23 (Too Far Left, Too Far Right, Too Long/Ditch, Too Short)

## Database Schema

Run migrations in order from `sql/` folder:
1. `schema.sql` - Base tables (sessions, rolls)
2. `add_players.sql` - User accounts with email verification
3. `add_clubs.sql` - Clubs and memberships
4. `add_challenges.sql` - Challenge system tables + sample challenges
5. `add_matches.sql` - Live match scoring tables

### Core Tables
- `players` - User accounts (email, password_hash, verified, hand preference)
- `sessions` - Practice sessions (player_id, hand, date, visibility)
- `rolls` - Individual bowl recordings (session_id, end_number, end_length, delivery, result, toucher)
- `clubs` - Bowling clubs with owner and members
- `club_members` - Club membership with roles (owner/admin/member)
- `challenges` - Pre-defined challenge templates (name, difficulty, sequences)
- `challenge_sequences` - Sequences within a challenge (end_length, delivery, bowl_count)
- `challenge_attempts` - Player attempts at challenges (score, completion status)
- `matches` - Live match records (club_id, game_type, status, total_ends)
- `match_teams` - Teams in a match (team_number, team_name)
- `match_players` - Players in a team (position, player_name)
- `match_ends` - End scores (end_number, scoring_team, shots)

## Frontend Patterns

- Vanilla JS with async/await fetch
- FormData for POST requests
- Toggle buttons use `data-field` and `data-value` attributes
- Anonymous users tracked via `bowlstracker_free_games` cookie (3 games/month limit)
- PWA support via `manifest.json` and `sw.js`

### Template System

The `Template` class provides reusable PHP components:
```php
Template::pageHead('Page Title', ['pages/stats.css'], '#2d5016', '../');
Template::header('Page Title', 'back-url.php', '<button>Right</button>');
Template::flash($flash);
Template::emptyState('No data', 'create.php', 'Create New');
Template::formError('errorId');
```

Templates are stored in `includes/templates/`:
- `page_head.php` - DOCTYPE + head section with meta tags
- `header.php` - App header with back button and title
- `flash.php` - Flash message display
- `empty_state.php` - Empty state with optional action button
- `form_error.php` / `form_message.php` - Form feedback elements

### CSS Architecture

- `css/styles.css` - Global styles, CSS variables, base components
- `css/pages/*.css` - Page-specific styles (match-scorer.css, challenge-play.css, stats.css, etc.)

Page CSS files are included via Template::pageHead():
```php
Template::pageHead('Stats', ['pages/stats.css']);
```

## Key Files

- `api/session.php` - Session CRUD + visibility toggle
- `api/roll.php` - Roll CRUD + undo (DELETE with `?undo=1`)
- `api/challenge.php` - Challenge API (list, start, roll, complete, history)
- `js/game.js` - Roll recording UI with end/bowl progression
- `js/challenge.js` - Challenge game UI with sequence progression
- `includes/Upload.php` - File uploads for club icons
- `includes/Challenge.php` - Challenge model with scoring system
- `includes/ChallengeAttempt.php` - Attempt tracking and progress
- `api/match.php` - Match API (create, start, end, complete, scores)
- `includes/GameMatch.php` - Match model with game type configs
- `matches/` - Live match scoring UI (index, create, score, view)

## Challenge System

Challenges are pre-defined practice routines with sequences of bowls at specific end lengths and deliveries.

### Scoring (per bowl)
- Centre: 10 points
- Level Left/Right: 7 points
- Long/Short Centre: 5 points
- Long Left/Right: 3 points
- Short Left/Right: 2 points
- Miss positions: 0 points
- Toucher bonus: +5 points

### Challenge Flow
1. Player starts challenge → creates attempt + hidden session
2. Each roll records: end_length, delivery (from sequence), result, toucher
3. Score calculated and accumulated per roll
4. Auto-completes when all sequences finished
5. Results page shows breakdown + attempt history

## Match System

Live match scoring for club members with real-time updates.

### Game Types
| Type | Players/Team | Bowls | Positions |
|------|--------------|-------|-----------|
| Singles | 1 | 4 | Skip |
| Pairs | 2 | 3-4 | Skip, Lead |
| Trips | 3 | 2-3 | Skip, Third, Lead |
| Fours | 4 | 2 | Skip, Third, Second, Lead |

### Match States
- `setup` - Match created, waiting to start
- `live` - Match in progress, scores being recorded
- `completed` - Match finished

### Access Control
- **Create/Delete**: Club owner or admin
- **Score**: Match creator or club admin
- **View**: Any club member

### Match Flow
1. Admin creates match → selects game type, teams, players
2. Start match → status becomes 'live'
3. Record ends → select scoring team + shots (1-8)
4. Viewers see auto-refresh (5 seconds) scoreboard
5. Complete match → status becomes 'completed'

## Competition System (Planned)

Club tournaments supporting Round Robin, Knockout, and Combined formats.

### Competition Formats
- **Round Robin**: Everyone plays everyone (or within groups)
- **Knockout**: Single elimination bracket with play-ins for non-power-of-2 sizes
- **Combined**: Group stage (round robin) → top qualifiers advance to knockout

### Database Tables (sql/add_competitions.sql)
- `competitions` - Tournament definitions (format, game_type, status, scoring)
- `competition_participants` - Registered teams/individuals
- `competition_participant_players` - Players in each team
- `competition_groups` - Groups for round robin
- `competition_fixtures` - Scheduled matches with bracket positions
- `competition_standings` - Cached standings with tie-breakers

### Key Models
- `Competition.php` - Main CRUD, status changes, permissions
- `CompetitionBracket.php` - Knockout bracket generation with play-ins
- `CompetitionRoundRobin.php` - Round robin scheduling (circle method)
- `CompetitionFixture.php` - Fixture management, match linking
- `CompetitionStandings.php` - Standings calculation

See plan file: `.claude/plans/wild-wiggling-teapot.md`
