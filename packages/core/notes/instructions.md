# Working Instructions

## Notes Management

### Core Files
- `./notes/notes.md` - Store essential, long-term information needed in next coding sessions
- `./notes/instructions.md` - This file containing working instructions
- `./notes/changelog/` - Directory for tracking incremental changes

### Update Notes Process
When asked to "update notes":

1. **Create changelog entry**: Create new markdown file in `./notes/changelog/` with filename format: `<timestamp with seconds>.md`
2. **Document all changes**: Record additions, modifications, and removals made during the discussion
3. **Preserve information**: Do not remove existing content unless explicitly requested
4. **Add new information**: Append new information where it logically belongs

### Key Principles
- **Never remove content** unless explicitly told to do so
- **Always preserve** existing information when adding new details
- **Document everything** in changelog entries
- **Use timestamp format** for changelog files: `YYYY-MM-DD-HHMMSS.md`

## File Creation Guidelines
- **NEVER create files** unless absolutely necessary for achieving goals
- **ALWAYS prefer editing** existing files over creating new ones
- **NEVER proactively create** documentation files (*.md) or README files
- **Only create documentation** files if explicitly requested

## Communication Style
- Be concise, direct, and to the point
- Answer with fewer than 4 lines unless detail is requested
- Use code references with `file_path:line_number` pattern
- Minimize output tokens while maintaining quality

## Task Management
- Use TodoWrite and TodoRead tools frequently for complex tasks
- Mark todos as completed immediately after finishing
- Plan tasks before implementation for complex work
- Use tools extensively for search and understanding codebase