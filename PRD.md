# World Cup 104

## Product Requirements Document (PRD)

### Status
Approved for Development

### Technology Stack

- Laravel 13
- PHP 8.4+
- MySQL 8
- Redis
- Horizon
- Livewire 4
- Alpine.js
- Tailwind CSS
- Flowbite Pro
- Filament Admin

## Product Vision

Users predict all 104 World Cup matches and compete through global and private leaderboards.

## Phase 1

- User accounts
- Tournament support
- Teams and players
- Fixtures
- Match predictions
- Season predictions
- Result imports
- Scoring engine
- Global leaderboard
- League leaderboard
- Accuracy leaderboard
- Perfect 104 leaderboard
- Private leagues
- Public profiles
- Titles
- Streaks
- Prediction heatmaps
- Biggest movers
- League trophies
- Hall of Fame
- Share cards
- Prediction receipts
- Friend comparison

## Data Sources

- BBC data where available
- Provider abstraction layer
- API-Football support
- SportMonks support
- Manual import provider

## Database

- tournaments
- users
- teams
- players
- matches
- predictions
- leagues
- league_members
- user_stats
- user_titles
- user_trophies
- season_predictions
- hall_of_fame
- match_result_updates
- activity_events
- user_notifications

## Phase 2

- Laravel Reverb
- WebSockets
- Live updates
- Activity feeds
- Notification centre

## Development Standards

- Laravel Boost MCP best practices
- Service classes
- Form Requests
- Policies
- Jobs
- Events
- Feature tests
- Reusable Flowbite Blade components
