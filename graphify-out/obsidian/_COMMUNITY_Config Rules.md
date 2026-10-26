---
type: community
members: 7
---

# Config Rules

**Members:** 7 nodes

## Members
- [[App environment Checks]] - rationale - .claude/skills/laravel-best-practices/rules/config.md
- [[Config Rules]] - document - .claude/skills/laravel-best-practices/rules/config.md
- [[Constants and Language Files]] - rationale - .claude/skills/laravel-best-practices/rules/config.md
- [[Encrypted Env or External Secrets]] - rationale - .claude/skills/laravel-best-practices/rules/config.md
- [[Schedule environments]] - rationale - .claude/skills/laravel-best-practices/rules/scheduling.md
- [[Secrets Out of Code]] - rationale - .claude/skills/laravel-best-practices/rules/security.md
- [[env Only in Config Files]] - rationale - .claude/skills/laravel-best-practices/rules/config.md

## Live Query (requires Dataview plugin)

```dataview
TABLE source_file, type FROM #community/Config_Rules
SORT file.name ASC
```

## Connections to other communities
- 1 edge to [[_COMMUNITY_HTTP Client & Testing Rules]]
- 1 edge to [[_COMMUNITY_Security Rules]]
- 1 edge to [[_COMMUNITY_Scheduling Rules]]

## Top bridge nodes
- [[Config Rules]] - degree 5, connects to 1 community
- [[Schedule environments]] - degree 2, connects to 1 community
- [[Secrets Out of Code]] - degree 2, connects to 1 community