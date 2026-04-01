# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Rolbal is a mobile-first PWA for tracking lawn bowls practice sessions. Players record roll positions on a visual grid and analyze performance statistics over time.

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

### Core Tables
- `players` - User accounts (email, password_hash, verified, hand preference)
- `sessions` - Practice sessions (player_id, hand, date, visibility)
- `rolls` - Individual bowl recordings (session_id, end_number, end_length, delivery, result, toucher)
- `clubs` - Bowling clubs with owner and members
- `club_members` - Club membership with roles (owner/admin/member)
- `challenges` - Pre-defined challenge templates (name, difficulty, sequences)
- `challenge_sequences` - Sequences within a challenge (end_length, delivery, bowl_count)
- `challenge_attempts` - Player attempts at challenges (score, completion status)

## Frontend Patterns

- Vanilla JS with async/await fetch
- FormData for POST requests
- Toggle buttons use `data-field` and `data-value` attributes
- Anonymous users tracked via `rolbal_free_games` cookie (3 games/month limit)
- PWA support via `manifest.json` and `sw.js`

## Key Files

- `api/session.php` - Session CRUD + visibility toggle
- `api/roll.php` - Roll CRUD + undo (DELETE with `?undo=1`)
- `api/challenge.php` - Challenge API (list, start, roll, complete, history)
- `js/game.js` - Roll recording UI with end/bowl progression
- `js/challenge.js` - Challenge game UI with sequence progression
- `includes/Upload.php` - File uploads for club icons
- `includes/Challenge.php` - Challenge model with scoring system
- `includes/ChallengeAttempt.php` - Attempt tracking and progress

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
