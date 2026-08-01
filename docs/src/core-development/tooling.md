# Developer Tooling

`bin/tools/` holds seven small Rust programs: three guards that run on every commit, and four
CLIs for talking to services a deployment depends on. They are built from source on your
machine - no binaries are committed.

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
