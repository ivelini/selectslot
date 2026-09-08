---
description: Ship changes (без обновления wiki)
---

# Ship changes

Commit and push current branch. Wiki (.llm-wiki/) при этом **не обновляется** — для коммита с синхронизацией wiki используйте `/ship-wiki`.

## Steps

1. Run `git status` and `git diff` to understand all current changes (staged and unstaged).
2. Determine the current branch: `git branch --show-current` — коммитим и пушим именно её. PR/MR не создаём.
3. Sync with remote if the branch has an upstream (`git rev-parse --abbrev-ref @{u}` succeeds): `git pull --ff-only origin <branch>`. If no upstream yet — skip (first push creates it). If pull fails — stop and report.
4. Based on the changes, generate a commit message in the project's style (look at recent commits for language/format).
5. Stage all changed files: `git add -A`
6. Commit with the generated message.
7. Push: `git push origin <branch>`
8. Report what was done: branch name, commit message, push result.

Important:
- If there are no changes to commit, stop and tell the user.
- The commit message should be in the same language and style as recent commits in the repo.
- Коммитим в текущую ветку — никакие PR/MR не создаются и не мержатся.
- Если изменения явно затрагивают бизнес-логику — в финальном отчёте напомнить пользователю, что wiki не обновлялась, и предложить `/ship-wiki` в следующий раз или отдельное обновление wiki.
