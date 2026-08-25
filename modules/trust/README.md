# Trust

A context-scoped reputation engine. It answers one question - *how much does this community trust this
member, in this context?* - and it answers it with a number nobody ever stored.

The module is **inert until a consumer describes a context**. On its own it has no contexts, no
surfaces and no effect. See [How to become a consumer](#how-to-become-a-consumer).

For how modules are built and guarded in general, see [modules/README.md](../README.md).

## Derived, never stored

The only persisted facts are member-to-member vouches (`mod_trust_grant`) and per-context
configuration (`mod_trust_context_config`). Scores are computed on read, every time, from three
sources:

| Source        | Comes from                                           | Contributed via         |
|---------------|------------------------------------------------------|-------------------------|
| Root points   | authority - who runs this community                  | `RootProviderInterface` |
| Action points | the consumer's own immutable record of what happened | `ActionSourceInterface` |
| Vouches       | members, through the module's own surfaces           | stored here             |

Only the third is the module's own. The first two are entirely the consumer's vocabulary - Trust has no
idea what the actions it scores actually are.

This is the whole design. An administrator who raises the points for an action from 5 to 8 sees the
*entire graph* move on the next page load, including members several vouches downstream of anyone who
ever performed it. That is only possible because no score was ever written down. There is deliberately
no score history and no audit trail; a "why is my score X" breakdown can be computed on demand if it is
ever wanted, never recorded.

A vouch is editable, an action is not. Vouches live in a mutable table with an `updatedAt`; actions come
from the consumer's own append-only record. That is exactly the line between *what I currently believe*
and *what happened*.

## The contract

### Inbound - what a consumer implements

All four are `#[AutoconfigureTag]`ed and resolved inside the module.

| Interface                   | Method                                                                                  | Chain shape                   |
|-----------------------------|-----------------------------------------------------------------------------------------|-------------------------------|
| `ContextDescriberInterface` | `describe(string $context): ?ContextDescriptor`, `describeAll(): iterable`              | first non-null wins           |
| `ActionSourceInterface`     | `describeActions($context)`, `replay($context)`, `getRevision($context): ?string`       | union, all sources contribute |
| `RootProviderInterface`     | `getRootPoints($context, int $userId): ?int`, `getRootUserIds($context): iterable<int>` | first non-null wins           |
| `AccessProviderInterface`   | `canView($context, int $userId): ?bool`, `canAdminister($context, int $userId): ?bool`  | first non-null wins           |

**A context no describer claims does not exist.** It cannot be scored, viewed or administered. That
single rule is what makes the module inert by default.

`getRevision()` is why no event wiring is needed anywhere: Trust *asks* its sources whether anything
moved, so a source never has to know Trust exists beyond implementing the interface. A source must be
replay-stable - the same context yields the same actions every time, which is what an append-only
record guarantees.

`getRootUserIds()` exists because the module has no way to enumerate members on its own; it only knows
the people its sources and its own vouch table name.

**Trust does not know what an action is.** An action key is an opaque string, exactly like a context
string: the module never parses one and ships no vocabulary of its own. A source both emits actions and
declares them through `describeActions()`, returning `ActionDescriptor(key, label, defaultPoints,
?quantityCap)` - and, like `replay()` and `getRevision()`, returning nothing for a context it does not
serve. **An action no source declares scores nothing.** That mirrors the context rule and keeps a typo
in a consumer from silently inflating anybody: the operator page lists any key that was replayed but
never declared.

When no `AccessProviderInterface` answers, access falls back to the viewer holding `ROLE_ADMIN`. A
context with no access provider is therefore visible to operators and to nobody else.

### Outbound - what a consumer calls

`TrustInterface`, and nothing else:

| Method                                 | Returns                                                                               |
|----------------------------------------|---------------------------------------------------------------------------------------|
| `getScore($context, int $userId)`      | `int`                                                                                 |
| `getScores($context)`                  | `array<int,int>` - the whole map for an administrator, the viewer's own row otherwise |
| `getBand($context, int $userId)`       | `TrustBand`                                                                           |
| `getVouchCount($context, int $userId)` | `int` - the only edge fact anyone else may see                                        |
| `meetsMinimum($context, int $userId)`  | `bool`                                                                                |
| `grant` / `revoke` / `getOutgoing`     | a member managing their own vouches                                                   |

Value types: `TrustAction`, `ActionDescriptor`, `ContextDescriptor`, `TrustConfig`, and the enums
`TrustLevel` (`Slight`, `Trusted`, `Absolute`) and `TrustBand`. Those two enums are the only closed
vocabularies in the contract, and both describe the module's own mechanics rather than any consumer's
domain.

## How a score is computed

```
base(u)   = min(maxScore, rootPoints(u) + actionPoints(u))
score⁰(u) = base(u)
score^{k+1}(b) = min(maxScore, base(b) + Σ_{a→b} pct(level) · score^k(a))
```

`score¹ ≥ score⁰` pointwise, because the sum is non-negative, and the update is monotone in its input,
so the sequence is non-decreasing; `min(maxScore, …)` bounds it above. A non-decreasing bounded
sequence converges. `ScoreCalculator` iterates until the largest change is under one point, or fifty
rounds, whichever comes first, then floors to integers.

**Cycles need no special case.** A and B vouching for each other feed one another a shrinking
contribution each round, and the cap bounds the result. There is no path enumeration anywhere.

Rules the calculator enforces: a self-edge is dropped rather than summed; a member with no root points,
no actions and no incoming vouches scores 0; the result is always the complete map for the context,
because computing one person's score means computing everybody's. A context above 10 000 members still
runs but logs a warning - a community that large wants a different design.

## Caching

One entry per context in the `cache.trust` pool, holding the score map plus a **combined revision**:
every action source's revision, the grant table's watermark for the context, and the configuration's
`updatedAt`. A read compares revisions and recomputes on mismatch; a vouch or configuration write
additionally drops the key for immediate effect. A per-request memo sits on top, so one page render
computes at most once. If the cache backend is unreachable the module computes directly and logs one
warning per service instance.

`app:trust:rebuild [--context=] [--dry-run]` recomputes and prints the map; `--dry-run` compares
against the cache and reports drift, exiting non-zero if it finds any. It is the standing proof that
nothing here is silently stateful.

## Privacy

Nobody may learn how many points flowed from whom. The public fact is a count.

- `getOutgoing()` takes the granting member as its *subject*. There is deliberately no method anywhere
  on the contract that returns another member's edges - privacy is a property of the surface, not of
  the callers' good manners.
- A raw score is visible to its owner and to a context administrator, nobody else. Everyone else sees a
  band and a vouch count. This is enforced in the service, not in the templates.
- The non-administrator table sort is **alphabetical**, because rank order is itself a disclosure.
- The vouch control is offered for every member other than yourself, regardless of whose score is
  higher. Hiding it above a threshold would itself reveal that the other person outranks you - exactly
  what the model exists to prevent. A low-standing member's vouch simply carries little weight.

## Surfaces

Trust owns no page in anyone else's application. It ships three Twig functions a consumer places where
it wants them, each rendering nothing when no describer claims the context or the viewer may not see it:

- `trust_table(context)` - members with band, vouch count, and the raw score only for an administrator
  or the viewer's own row.
- `trust_vouch_control(context, userId)` - the four-way select, POST + CSRF.
- `trust_badge(context, userId)` - band plus vouch count, to drop next to a member's name.

It also owns one page of its own: `/admin/trust`, `ROLE_ADMIN`. That is the **operator** surface - a
global view for inspecting and fixing any context, and the only way to reach the module before a
consumer exists. It does not replace `canAdminister`: per-context administration by someone who is not
a platform administrator happens through the fragments a consumer places, gated by the access chain.

## Configuration

Per context, with neutral defaults so a context with no row still works.

| Setting                | Default        | Meaning                                                    |
|------------------------|----------------|------------------------------------------------------------|
| `maxScore`             | 1000           | hard cap on any score                                      |
| `percentSlight`        | 10             | share of the voucher's score passed on                     |
| `percentTrusted`       | 25             |                                                            |
| `percentAbsolute`      | 50             |                                                            |
| `rootPointsPrimary`    | 1000           | root points for the top authority                          |
| `rootPointsSecondary`  | 500            | root points one rung down                                  |
| `pointsPerAction`      | `[]`           | per-action override of the descriptor's `defaultPoints`    |
| `capsPerAction`        | `[]`           | per-action override of the descriptor's `quantityCap`      |
| `minimumToParticipate` | 0              | 0 disables the gate                                        |
| `bandThresholds`       | 50 / 200 / 500 | `New` / `Known` / `Trusted` / `Highly trusted`             |

**The two action maps hold overrides only, never the full set.** Points and caps live with the
descriptor that declares them, and configuration only departs from that. Storing the whole map instead
would mean a consumer adding a new action later scored 0 in every context an administrator had ever
saved; with overrides, a new action arrives carrying its own default and can be tuned afterwards.

A `quantityCap` bounds the summed quantity of one action per member before points are applied - the
mechanism behind a slow tenure drip that must not reach the cap on its own. `null` means uncapped.

`TrustConfig` validates every value in its constructor, so an out-of-range setting cannot reach the
database.

Vouching *completely* from the secondary rung passes on 250 - enough to matter, not enough to rival the
primary anchor. That is the reasoning behind the two root defaults.

## How to become a consumer

1. Implement `ContextDescriberInterface` and mint a context string of your own shape. The module never
   parses it.
2. Implement whichever of `ActionSourceInterface` and `RootProviderInterface` apply, plus
   `AccessProviderInterface` unless operator-only access is what you want. An action source declares its
   own action keys, labels and default points - the module has no built-in ones.
3. Place the Twig fragments on your own page.
4. Call `TrustInterface` where a decision depends on standing.

`modules/trust/tests/Stub/` is a complete four-interface consumer in about eighty lines, registered
through `config/services_test.yaml` in the test environment only. It is the reference to copy.

## Out of scope

Negative trust, distrust, reports and bans - the graph only adds. Cross-context transfer: a score in
one context means nothing in another, and there is no global reputation. Decay over time. Score
history. Where a score appears on a member's profile - that is the consumer's decision, not the
module's.
