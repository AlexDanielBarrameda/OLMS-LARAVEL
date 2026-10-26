---
type: community
members: 5
---

# Logging Config

**Members:** 5 nodes

## Members
- [[MonologHandlerNullHandler]] - code
- [[MonologHandlerStreamHandler]] - code
- [[MonologHandlerSyslogUdpHandler]] - code
- [[MonologProcessorPsrLogMessageProcessor]] - code
- [[logging.php]] - code - config/logging.php

## Live Query (requires Dataview plugin)

```dataview
TABLE source_file, type FROM #community/Logging_Config
SORT file.name ASC
```
