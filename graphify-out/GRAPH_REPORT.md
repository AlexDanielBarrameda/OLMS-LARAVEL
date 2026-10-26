# Graph Report - olms_bookgarden  (2026-10-23)

## Corpus Check
- Corpus is ~24,322 words - fits in a single context window. You may not need a graph.

## Summary
- 585 nodes · 828 edges · 59 communities (22 shown, 37 thin omitted)
- Extraction: 93% EXTRACTED · 7% INFERRED · 0% AMBIGUOUS · INFERRED: 54 edges (avg confidence: 0.8)
- Token cost: 143,939 input · 0 output

## Community Hubs (Navigation)
- Book Resource Pages
- Database Migrations
- Blade & Events Practices
- Models & Policies
- Filament Panels & Dashboard
- Pest & Tailwind Skills
- Advanced Queries & Collections
- Edit Pages & Tables
- NPM Frontend Tooling
- Book Widgets & Login
- Factories, Seeders, Config
- Stats Overview Widgets
- HTTP Client & Testing Rules
- Table Configurations
- Activity Table Widgets
- Architecture Rules
- Queue Jobs & Retries
- Profile & User Dashboard
- Borrowing Charts
- Transaction Model & Info Widget
- Composer Dev Dependencies
- Caching Rules
- Migration Rules
- Routing & Validation Rules
- Security Rules
- App Bootstrap
- Composer Metadata
- Composer Scripts
- Config Rules
- Eloquent Rules
- Error Handling Rules
- Scheduling Rules
- Composer Config
- Login & Web Routes
- App Service Provider
- PSR-4 Autoload
- Composer Requirements
- Test Autoload
- Package Discovery
- Test Case Base
- Base Controller
- Login View

## God Nodes (most connected - your core abstractions)
1. `User` - 32 edges
2. `Laravel Best Practices Skill` - 21 edges
3. `Transaction` - 15 edges
4. `Book` - 14 edges
5. `UserResource` - 11 edges
6. `Architecture Rules` - 11 edges
7. `Security Rules` - 11 edges
8. `BookResource` - 10 edges
9. `TransactionResource` - 10 edges
10. `require-dev` - 10 edges

## Surprising Connections (you probably didn't know these)
- `robots.txt Allow-All Crawl Policy` --conceptually_related_to--> `Laravel Framework`  [INFERRED]
  public/robots.txt → README.md
- `Pest Testing Skill` --references--> `Laravel Framework`  [INFERRED]
  .claude/skills/pest-testing/SKILL.md → README.md
- `Tailwind CSS Development Skill` --conceptually_related_to--> `Laravel Framework`  [INFERRED]
  .claude/skills/tailwindcss-development/SKILL.md → README.md
- `{closure#4}()` --references--> `Book`  [EXTRACTED]
  app/Filament/User/Widgets/AvailableBooksWidget.php → app/Models/Book.php
- `Chunking Large Datasets` --semantically_similar_to--> `lazyById`  [INFERRED] [semantically similar]
  .claude/skills/laravel-best-practices/rules/db-performance.md → .claude/skills/laravel-best-practices/rules/collections.md

## Import Cycles
- None detected.

## Hyperedges (group relationships)
- **After-Commit Dispatch for Transaction Safety** — _claude_skills_laravel_best_practices_rules_events_notifications_shoulddispatchaftercommit, _claude_skills_laravel_best_practices_rules_events_notifications_notification_aftercommit, _claude_skills_laravel_best_practices_rules_mail_mailable_aftercommit [INFERRED 0.85]
- **N+1 Query Prevention Techniques** — _claude_skills_laravel_best_practices_rules_db_performance_eager_loading, _claude_skills_laravel_best_practices_rules_db_performance_preventlazyloading, _claude_skills_laravel_best_practices_rules_db_performance_withcount, _claude_skills_laravel_best_practices_rules_advanced_queries_setrelation_circular_n_1, _claude_skills_laravel_best_practices_rules_advanced_queries_addselect_subqueries [INFERRED 0.85]
- **Concurrency and Duplicate-Execution Control** — _claude_skills_laravel_best_practices_rules_architecture_atomic_locks_for_race_conditions, _claude_skills_laravel_best_practices_rules_queue_jobs_shouldbeunique, _claude_skills_laravel_best_practices_rules_scheduling_withoutoverlapping, _claude_skills_laravel_best_practices_rules_scheduling_ononeserver [INFERRED 0.75]
- **Tailwind v3 to v4 Migration Rules** — _claude_skills_tailwindcss_development_skill_css_first_configuration, _claude_skills_tailwindcss_development_skill_import_syntax, _claude_skills_tailwindcss_development_skill_replaced_utilities [EXTRACTED 1.00]
- **Pest 3 Feature Set** — _claude_skills_pest_testing_skill_datasets, _claude_skills_pest_testing_skill_architecture_testing, _claude_skills_pest_testing_skill_type_coverage, _claude_skills_pest_testing_skill_pest_mocking [EXTRACTED 1.00]

## Communities (59 total, 37 thin omitted)

### Community 0 - "Book Resource Pages"
Cohesion: 0.06
Nodes (13): BookResource, CreateBook, ListBooks, BookForm, BooksTable, CreateTransaction, ListTransactions, TransactionForm (+5 more)

### Community 1 - "Database Migrations"
Cohesion: 0.10
Nodes (14): {closure#1}(), {closure#2}(), {closure#3}(), {closure#1}(), {closure#2}(), {closure#1}(), {closure#2}(), {closure#3}() (+6 more)

### Community 2 - "Blade & Events Practices"
Cohesion: 0.07
Nodes (4): Blade Views Rules, Events Notifications Rules, Mail Rules, Style Rules

### Community 3 - "Models & Policies"
Cohesion: 0.15
Nodes (4): Book, User, BookPolicy, UserPolicy

### Community 4 - "Filament Panels & Dashboard"
Cohesion: 0.12
Nodes (4): AdminDashboard, ExpandedAccountWidget, AdminPanelProvider, UserPanelProvider

### Community 5 - "Pest & Tailwind Skills"
Cohesion: 0.09
Nodes (18): Architecture Testing (arch()), Pest Datasets, Pest 3, Pest Testing Skill, php artisan test --compact, search-docs tool, Type Coverage, Dark Mode (dark: variant) (+10 more)

### Community 6 - "Advanced Queries & Collections"
Cohesion: 0.10
Nodes (3): Advanced Queries Rules, Collections Rules, Db Performance Rules

### Community 7 - "Edit Pages & Tables"
Cohesion: 0.14
Nodes (3): EditBook, EditTransaction, EditUser

### Community 8 - "NPM Frontend Tooling"
Cohesion: 0.09
Nodes (19): devDependencies, axios, concurrently, laravel-vite-plugin, tailwindcss, @tailwindcss/vite, vite, private (+11 more)

### Community 9 - "Book Widgets & Login"
Cohesion: 0.17
Nodes (3): {closure#4}(), {closure#5}(), {closure#3}()

### Community 11 - "Stats Overview Widgets"
Cohesion: 0.21
Nodes (4): AvailableBooksCount, MyBorrowedBooksCount, ProfileStatsWidget, DashboardStats

### Community 12 - "HTTP Client & Testing Rules"
Cohesion: 0.18
Nodes (3): Http Client Rules, Testing Rules, Laravel Best Practices Skill

### Community 13 - "Table Configurations"
Cohesion: 0.21
Nodes (3): TransactionsTable, UsersTable, MyBorrowedBooksWidget

### Community 14 - "Activity Table Widgets"
Cohesion: 0.21
Nodes (3): AvailableBooksWidget, BorrowingHistoryWidget, RecentActivity

### Community 20 - "Composer Dev Dependencies"
Cohesion: 0.20
Nodes (10): require-dev, fakerphp/faker, laravel/boost, laravel/pail, laravel/pint, laravel/sail, mockery/mockery, nunomaduro/collision (+2 more)

### Community 26 - "Composer Metadata"
Cohesion: 0.22
Nodes (8): description, keywords, license, minimum-stability, name, prefer-stable, $schema, type

### Community 27 - "Composer Scripts"
Cohesion: 0.22
Nodes (9): scripts, dev, post-autoload-dump, post-create-project-cmd, post-root-package-install, post-update-cmd, pre-package-uninstall, setup (+1 more)

### Community 32 - "Composer Config"
Cohesion: 0.29
Nodes (7): pestphp/pest-plugin, php-http/discovery, config, allow-plugins, optimize-autoloader, preferred-install, sort-packages

### Community 35 - "PSR-4 Autoload"
Cohesion: 0.40
Nodes (5): autoload, psr-4, App\\, Database\\Factories\\, Database\\Seeders\\

### Community 36 - "Composer Requirements"
Cohesion: 0.40
Nodes (5): require, filament/filament, laravel/framework, laravel/tinker, php

### Community 39 - "Test Autoload"
Cohesion: 0.67
Nodes (3): autoload-dev, psr-4, Tests\\

### Community 40 - "Package Discovery"
Cohesion: 0.67
Nodes (3): extra, laravel, dont-discover

## Knowledge Gaps
- **14 isolated node(s):** `concurrently`, `tailwindcss`, `$set(`, `Pest Datasets`, `Type Coverage` (+9 more)
  These have ≤1 connection - possible missing edges or undocumented components. (Counts symbols only; 257 node(s) total have ≤1 connection when file, concept and rationale nodes are included.)
- **37 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **Why does `User` connect `Models & Policies` to `Book Resource Pages`, `Filament Panels & Dashboard`, `Book Widgets & Login`, `Factories, Seeders, Config`, `Stats Overview Widgets`?**
  _High betweenness centrality (0.092) - this node is a cross-community bridge._
- **What connects `concurrently`, `tailwindcss`, `$set(` to the rest of the system?**
  _14 weakly-connected nodes found - possible documentation gaps or missing edges._
- **Should `Book Resource Pages` be split into smaller, more focused modules?**
  _Cohesion score 0.06328320802005012 - nodes in this community are weakly interconnected._
- **Why does `Laravel Best Practices Skill` connect `HTTP Client & Testing Rules` to `Blade & Events Practices`, `Advanced Queries & Collections`, `Architecture Rules`, `Queue Jobs & Retries`, `Caching Rules`, `Migration Rules`, `Routing & Validation Rules`, `Security Rules`, `Config Rules`, `Eloquent Rules`, `Error Handling Rules`, `Scheduling Rules`?**
  _High betweenness centrality (0.060) - this node is a cross-community bridge._
- **Should `Database Migrations` be split into smaller, more focused modules?**
  _Cohesion score 0.09747899159663866 - nodes in this community are weakly interconnected._
- **Why does `Transaction` connect `Transaction Model & Info Widget` to `Book Resource Pages`, `Book Widgets & Login`, `Stats Overview Widgets`, `Activity Table Widgets`, `Borrowing Charts`?**
  _High betweenness centrality (0.027) - this node is a cross-community bridge._
- **Should `Blade & Events Practices` be split into smaller, more focused modules?**
  _Cohesion score 0.06881720430107527 - nodes in this community are weakly interconnected._