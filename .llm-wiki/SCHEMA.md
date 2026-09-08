# .llm-wiki — LLM Knowledge Base

## Структура

```
.llm-wiki/raw/<topic>/YYYY-MM-DD-slug.md   # неизменяемые источники (только чтение)
.llm-wiki/wiki/<topic>/<article>.md        # скомпилированные статьи (один уровень тем)
.llm-wiki/wiki/index.md                    # индекс: строка на статью, группировка по темам
.llm-wiki/wiki/log.md                      # append-only журнал: ## [YYYY-MM-DD] <операция> | <статья>
.llm-wiki/SCHEMA.md                        # этот файл
```

## Правила

- `raw/` — читать, никогда не редактировать. Имя файла: `YYYY-MM-DD-slug.md`, метадата Source/Collected/Published.
- `wiki/` — изменять только через скил `.claude/skills/llm-wiki` (Ingest: Fetch → Compile → Cascade → Post-Ingest).
- `index.md` и `log.md` обновляются только скилом (Ingest/Archive/Lint). Ручные правки запрещены.
- Относительные ссылки внутри wiki/ (из `wiki/<topic>/` в raw/ — `../../raw/<topic>/<file>.md`); в чате — от корня проекта.
- Язык статей: ru.

## Форматы

- Статья: `.claude/skills/llm-wiki/references/article-template.md`
- Raw-источник: `.../references/raw-template.md`
- Индекс: `.../references/index-template.md`
- Архив: `.../references/archive-template.md`
- Лог: `## [YYYY-MM-DD] ingest | <статья>` (или query/lint), строки `- Updated: <статья>` при cascade-обновлениях
