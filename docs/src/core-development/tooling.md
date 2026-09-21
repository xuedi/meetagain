# Developer Tooling

`bin/tools/` holds small Rust programs: guards that run on every commit, CLIs for talking to
services a deployment depends on, and `output-filter`, which turns the output of a `just` recipe
into one status line per task. They are built from source on your machine - no binaries are
committed.

```bash
just buildTools   # compile everything into bin/tools/bin/
just testTools    # run their test suite
```

`just install` already runs `just buildTools`, so a fresh clone that follows
[Getting Started](../getting-started.md) ends up with working guards.

!!! note "No Rust toolchain?"
    You can still work on the project. The hook scripts print a warning to stderr and let the
    commit through when a binary is missing - they never block you.

    If you would rather not see the warnings, delete the scripts you don't want from
    `bin/commit-hooks/`, or just `chmod -x` them - the dispatcher skips anything that isn't
    executable. That directory is gitignored and every file in it is a copy of
    `tests/config/commit-hooks/`, so nothing is lost and `just install` puts them back.

    Both routes also remove the check from `just test`, which runs the same chain. CI runs the
    guards regardless, so a deleted hook postpones the failure rather than avoiding it. Install
    [rustup](https://rustup.rs/) when you want them locally.

---

## The guards

Each runs as a numbered script in `bin/commit-hooks/`, which `bin/commit-hooks.sh` executes for
both the git pre-commit hook and `just test`. All three scan the **whole tree** rather than just
the staged files, so a violation that arrived through a merge or a `--no-verify` commit is still
caught. A full scan takes single-digit milliseconds.

### `leak-guard` (slot 01)

Refuses to let two kinds of string leave the repository.

**Credentials.** Regexes for shapes this project issues or consumes: its own access tokens,
payment-provider keys, PEM private-key headers, AWS access key ids, SendGrid and Mailgun keys,
GitHub tokens, `APP_SECRET` assignments, and connection DSNs carrying an inline password.

A finding prints the pattern name and the location and **never the matched text**:

```
config/Services.php:41: [aws-access-key-id] credential-shaped value (content withheld)
```

Echoing the value would put the key into your terminal, your scrollback and possibly a CI log.
When you hit one: remove the string **and rotate the key**. Anything that reached the working
tree has to be treated as compromised.

Test doubles sometimes have to be credential-shaped, because the client library validates the
shape before it will instantiate. For those, add one `ALLOW=path::pattern-name` entry in
`config/tools/leak-guard.dist` - it suppresses a single pattern at a single path, so every other
pattern still applies to that file. Do not exclude the file.

**Vocabulary.** A substring list a deployment can decide not to publish. The core repository
ships no such list; it comes from the private overlay described below.

### `mermaid-guard` (slot 05)

Lints mermaid code blocks in markdown for syntax GitHub silently fails to render: unquoted
labels containing `"`, unknown diagram types, unclosed fences, empty blocks. Scans the roots in
`config/tools/mermaid-guard.dist`.

### `comment-guard` (slot 06)

Enforces the project's comment policy over PHP: comments are scarce by default, and one that is
not allowed by shape must be justified by a line in that repository's
`tests/importantCodeComments.txt`. Run `comment-guard --suggest` for ready-to-fill lines.
Twig, JavaScript and SCSS are out of its scope.

---

## Skipping tests that already passed: `test-stamp`

A green `just test` records the working tree in `testPassing.lock` (gitignored, repo root). When
you commit afterwards, the pre-commit hook asks `test-stamp check` first. If no file has changed
since, it runs only the four guards and skips static checks and the test suites:

```text
Tests skipped - the tree matches testPassing.lock (just test to force)
Leak guard ...................................... OK
```

- **Content, not timestamps.** Every file is hashed with BLAKE3 together with its path and its
  executable bit. `touch`, staging and committing change nothing; editing a byte or losing a `+x`
  does. `.git/` is never read, so a partial commit leaves the stamp valid for the next one.
- **Which files count** is set in `config/tools/test-stamp.dist`. `SCAN_ROOTS` are walked with
  their repository's git ignore rules, `INCLUDE_IGNORED` pulls back ignored files that still
  change a result (the installed hooks, `.env.local`, the enabled-plugin lists), `EXCLUDE` drops
  prefixes only an always-running guard checks. `test-stamp files` prints the full list.
- **The guards always run.** `docs-guard` and `mermaid-guard` look at staged markdown only, which a
  green `just test` says nothing about. `ALWAYS_RUN` names the hooks that run on a fresh stamp.
- **A stale stamp says why:** the hook lists up to ten changed, added or removed paths, runs the
  whole chain and, when it passes, writes a new stamp.
- **Edits during a run are never stamped.** The fingerprint is taken before the first hook and
  compared again after the last one; if they differ, no stamp is written.
- **`just test` always runs everything**, and so does a clone without the built binary.

The stamp proves what the hooks always proved: that the *working tree* passed. Neither checks the
staged snapshot on its own.

---

## Quieter recipes with `output-filter`

Every recipe line runs through `output-filter`: the justfile's `set shell` points at
`bin/just-shell`, which falls back to a plain shell until the binary is built. Commands that have
a label print one line per task instead of the command and its output:

```text
Install Composer packages ....................... OK   3s
Compile assets .................................. OK   3s
Run migrations .................................. FAILED

the full log of what failed can be found here: justFail.log
```

- **Everything without a label runs untouched**, so recipes whose output is the point - the
  tests, `just app ...`, a database query - look as they always did. A command with no rule gets a
  `$ command` heading and is recorded in the gitignored `output-filter.unknown.json`, ready to be
  labelled.
- **Rules live in `config/tools/output-filter.dist.json`.** `rules` give a `label` to a list of
  `commands`, `fold` merges a command silently into the line before it, `ignore` runs a command
  untouched without a heading, and `aliases` shorten the expanded `PHP` and `DOCKER` prefixes the
  patterns are written in. `*` matches anything and the longest matching pattern wins. A gitignored
  `output-filter.local.json` next to it can add your own. `output-filter --check` validates both.
- **`just test` and the pre-commit hook print one line per hook.** `bin/commit-hooks.sh` runs each
  script in `bin/commit-hooks/` through the same shim. `just check` and `just testUnit` still show
  their full output when you run them directly.
- **When a step fails**, the run stops where just stops, and `justFail.log` in the repo root holds
  the failed command and its full output.
- **`just debug=1 <recipe>`** shows every command and its full output again.

---

## Configuration

Every tool reads config from the repo root, in two layers:

| File                          | Committed | Holds                                                |
|-------------------------------|-----------|------------------------------------------------------|
| `config/tools/<name>.dist`    | yes       | Rules and non-secret defaults. **Required.**         |
| `config/tools/<name>.local`   | no        | Optional overlay: credentials and private rules      |

The merge rule: **list keys concatenate `.dist` then `.local`; scalar keys take `.local` and
fall back to `.dist`.** So an overlay adds a scan root or a pattern instead of replacing the
public set, while still being able to override a single number or URL.

A missing `.dist` is an error (exit code 2). A missing `.local` is normal - every tool runs on
its `.dist` alone, and the operator CLIs simply report which credential key is unset.

Never put a credential in a `.dist`. That is what the overlay is for.

!!! warning "`.local` files are hand-maintained and unbacked"
    Nothing generates them and nothing else holds their values, so anything you put in one
    belongs in a password manager as well. To set one up, copy the `.dist` and fill in the keys
    you need - or write only the lines that differ, since the rest falls back to the `.dist`.
    `output-filter` keeps the same two layers as JSON: `output-filter.dist.json` and the
    gitignored `output-filter.local.json`.

---

## Adding a tool

1. Create `bin/tools/<name>/` with a `Cargo.toml` that inherits `version` and `edition` from the
   workspace, and depends on `toolconfig.workspace = true` if it needs config.
2. Add the crate to `members` in `bin/tools/Cargo.toml`.
3. Add `config/tools/<name>.dist`, documenting every key; leave credential keys empty.
4. `just buildTools` picks it up - the recipe installs every crate that has a
   `bin/tools/<name>/src/main.rs`.

A new guard also needs a script in `tests/config/commit-hooks/`; copy
`01-leak-guard.bash` for the missing-binary warning and the failure banner.
