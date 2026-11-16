# Laravel OOP Lab — Build Guide

This document is the implementation plan for this portfolio project. Build every
step yourself; it intentionally describes responsibilities, acceptance criteria,
and commands instead of supplying the application source code.

## 1. Project outcome

Build a Laravel application with four small, independently understandable labs:

1. **Payment gateway** — dependency inversion and container bindings.
2. **Notifications** — inheritance, traits, composition, and polymorphism.
3. **Pricing engine** — readonly value objects, enums, strategy, and pipeline.
4. **Order workflow** — state objects, events, queued listeners, and Eloquent.

The goal is not to mimic a production payment or delivery platform. The goal is
to have small, well-tested examples that let you explain each OOP and Laravel
concept using code you own.

## 2. Current environment

The project currently uses Laravel 13, PHP 8.4 locally, PHP 8.5 inside Sail,
Pest 4, PHPUnit 12, and Laravel Sail. These PHP versions support all features
needed here, including enums, readonly classes, constructor property promotion,
and union types.

Start containers:

```bash
./vendor/bin/sail up -d
```

Stop containers:

```bash
./vendor/bin/sail down
```

Run an Artisan command inside Docker:

```bash
./vendor/bin/sail artisan about
```

Run the test suite:

```bash
./vendor/bin/sail pest
```

The local application URL is `http://localhost:8080`. Do not use port 80 for
this project because another local application already owns it.

## 3. Non-negotiable working rules

- Create Laravel files with `php artisan make:` where an Artisan generator
  exists. Always add `--no-interaction`.
- Prefer constructor injection over `app()`, `resolve()`, or static service
  lookups in application code.
- Depend on contracts at external boundaries: payment processors and message
  transports are good examples.
- Do not make real network calls in unit tests. Use the project-owned fake or
  Laravel's framework fakes.
- Add tests with `php artisan make:test --pest`; place unit tests at the same
  relative path as the corresponding class.
- Run the narrowest relevant test after each change. Format changed PHP files
  with `vendor/bin/pint --dirty --format agent`.
- Do not commit `.env`, real API keys, or a production payment credential.
- Avoid abstractions that have only one meaningful implementation unless the
  class represents a real boundary you deliberately want to replace in tests or
  environments.

## 4. Recommended layout

Keep the four labs visibly separate while leaving Laravel framework concerns in
their normal locations.

```text
app/
├── Concepts/
│   ├── Payment/
│   ├── Notifications/
│   ├── Pricing/
│   └── Orders/
├── Actions/
├── DTOs/
├── Events/
├── Http/
│   ├── Controllers/
│   └── Requests/
├── Listeners/
├── Models/
├── Policies/
├── Providers/
└── States/

tests/
├── Feature/
└── Unit/
```

Use `app/Concepts` for the pure OOP examples. Put Laravel entry points such as
controllers, Form Requests, event listeners, models, and policies in their
conventional Laravel directories. This makes it easy to distinguish domain code
from framework glue.

## 5. Milestone sequence

Do milestones in this order. Do not start a later milestone until its test suite
is green and you can explain the earlier one.

| Milestone | Deliverable | Primary concepts |
| --- | --- | --- |
| 0 | Environment baseline | Sail, Pest, migrations, Git |
| 1 | Payment gateway | interface, DI, provider, fake |
| 2 | Notifications | abstract class, trait, polymorphism |
| 3 | Pricing engine | value objects, enum, strategy, pipeline |
| 4 | Order workflow | states, events, listeners, queues |
| 5 | Laravel integration | Eloquent, requests, DTOs, policies, middleware |
| 6 | Debugging and quality | Xdebug, coverage, CI |
| 7 | Portfolio polish | README, diagrams, screenshots |

## 6. Milestone 0 — Establish a reliable baseline

### What to do

1. Confirm Sail starts and the home page loads at `http://localhost:8080`.
2. Run migrations.
3. Run the starter test suite.
4. Initialize Git if it is not already initialized.
5. Make a baseline commit before changing project behavior.

Commands:

```bash
./vendor/bin/sail artisan migrate
```

```bash
./vendor/bin/sail pest
```

```bash
git status
```

### Expected result

- MySQL accepts connections as the local `sail` user.
- Starter tests pass.
- You can refresh the Laravel welcome page without a database exception.

### Do not expect

- No business pages, routes, or API endpoints exist yet.
- Mailpit, Redis, and the queue are running services, but no application code
  uses them yet.

## 7. Milestone 1 — Payment gateway

### Learning objective

Make a controller or action able to charge an order without knowing whether the
implementation is Stripe, a sandbox gateway, or an in-memory fake.

### Build order

1. Define the payment contract.
2. Define immutable input and output DTOs.
3. Write the fake implementation.
4. Write the charge action against the contract.
5. Test the action with the fake.
6. Add the Stripe adapter shape, without adding real credentials.
7. Register environment-aware bindings in a provider.
8. Add the Form Request, controller, route, and feature tests.

### Responsibilities

| Component | Owns | Must not own |
| --- | --- | --- |
| `PaymentGateway` contract | The charge capability and return type | Stripe-specific request details |
| `ChargeData` DTO | Validated charge input | HTTP request access |
| `PaymentResult` DTO | Gateway outcome, transaction/reference data | Controller responses |
| `FakeGateway` | Deterministic successful/failing behavior for tests | Network calls |
| `StripeGateway` | Translation between the contract and Stripe | Business workflow |
| `ChargePayment` action | One charging use case | Container configuration |
| Payment provider | Contract-to-implementation binding | Charge business logic |
| Form Request | Validation and authorization for the request | Gateway calls |
| Controller | HTTP in/out coordination | Stripe SDK details |

### Why the interface matters

The action depends on `PaymentGateway`, not `StripeGateway`. This is dependency
inversion: high-level business behavior depends on an abstraction. The fake can
replace Stripe without changing the action, demonstrating Liskov substitution.

### Environment binding plan

- `local` and `testing`: bind `PaymentGateway` to `FakeGateway`.
- `production`: bind `PaymentGateway` to `StripeGateway`.
- Keep secrets in configuration loaded from environment variables; never read
  `env()` directly from a gateway class.

### Tests to write

- Unit: a successful fake charge returns the expected result.
- Unit: a declined fake charge returns or throws the chosen domain outcome.
- Unit: the action calls its injected gateway exactly once with the expected
  charge data.
- Feature: a valid request receives the chosen success response.
- Feature: invalid amount, currency, and payment token are rejected by the
  Form Request.
- Feature: the test environment resolves the fake, never the Stripe adapter.

### Expected result

You can change the provider binding and use the same controller/action code
with another gateway. Unit tests run with no database and no network.

### Do not expect

- Do not process a real card or install a real Stripe SDK in the first version.
- Do not place an `if ($gateway === 'stripe')` chain in the controller.
- Do not inject a facade into the controller to bypass the contract.

## 8. Milestone 2 — Notification channels

### Learning objective

Send a message through a growing list of channels without changing the sender
each time a channel is added.

### Build order

1. Create an abstract `Channel` base class for shared channel behavior.
2. Create `EmailChannel`, `SmsChannel`, and `SlackChannel` children.
3. Add a `HasRetries` trait for reusable retry behavior.
4. Create an immutable notification DTO.
5. Build `NotificationSender` by composition: it receives a collection of
   channels.
6. Make the sender invokable with `__invoke` after the normal method is clear.
7. Add tests for each child and for the sender's collaboration.

### Design decisions to demonstrate

- **Abstract class:** use it when channels share meaningful implementation or
  protected behavior, not merely a method name.
- **Inheritance:** email, SMS, and Slack are kinds of channel.
- **Composition:** the sender has channels; it is not a kind of channel.
- **Trait:** retries are reusable behavior, not an identity in the class tree.
- **Polymorphism:** the sender calls the same sending operation for every
  channel with no channel-type `if` or `match` chain.
- **`__invoke`:** an instance can be called like a function, making a focused
  application service pleasant to use without hiding its dependencies.
- **Static versus late static binding:** create a small, tested channel-label
  example to show why `static::` honors a child class while `self::` does not.

### Tests to write

- Each channel receives the same DTO and reports its own delivery result.
- A failing channel uses the retry behavior the expected number of times.
- The sender sends through all supplied channels.
- Adding a fourth fake channel requires no sender modification.

### Do not expect

- Do not use real SMS, Slack, or email services in these unit tests.
- Do not turn the trait into a place for unrelated configuration or state.

## 9. Milestone 3 — Pricing engine

### Learning objective

Calculate a price from independently replaceable rules, while making invalid
currency and percentage values hard to represent.

### Build order

1. Create a `Currency` backed enum with the currencies your lab supports.
2. Create a readonly `Money` value object.
3. Create a readonly `Percentage` value object.
4. Define a `PricingRule` strategy contract.
5. Implement small rules: fixed discount, percentage discount, tax, shipping.
6. Create a pricing context DTO.
7. Create a pipeline that applies rules in a declared order.
8. Test values, each rule, and the whole pipeline.

### Important design choices

- Store money in the smallest unit (for example, cents) as an integer. Do not
  use floating-point arithmetic for money.
- Let `Money` own arithmetic and currency compatibility checks.
- Let `Percentage` reject values outside the chosen allowed range.
- Let `Currency` replace unvalidated strings such as `"USDD"`.
- A pricing rule knows one transformation only. The pipeline owns sequencing.

### Tests to write

- Adding/subtracting money with compatible currencies.
- Rejecting an operation across incompatible currencies.
- Percentage limits: zero, a normal value, the maximum, and an invalid value.
- Rounding at a deliberately chosen rule boundary.
- Each pricing rule in isolation.
- Pipeline order: discount-before-tax and tax-before-discount should be
  intentionally different if business rules say so.

### Do not expect

- Do not build every possible tax jurisdiction or coupon type.
- Do not use decimal floats merely because they make a first implementation
  shorter.
- Do not put every pricing rule in one large conditional method.

## 10. Milestone 4 — Order workflow

### Learning objective

Represent a finite order lifecycle and make invalid transitions impossible to
perform through ordinary application code.

### Build order

1. Create the `OrderStatus` enum.
2. Write an allowed-transition map and tests for it.
3. Create a base order-state abstraction and one state class per status.
4. Create a transition action that delegates to the current state.
5. Add the `Order` model, migration, factory, relationships, casts, and scopes.
6. Dispatch `OrderShipped` after the transition to shipped succeeds.
7. Add exactly three queued listeners: shipment email, warehouse notification,
   and analytics recording.
8. Test valid transitions, rejected transitions, event dispatch, and queued
   listeners.

### Suggested statuses

Keep the first lifecycle deliberately small: `Pending`, `Paid`, `Packed`,
`Shipped`, `Delivered`, and `Cancelled`. Decide and document exactly where
cancellation is allowed. For example, it may be allowed before shipment but not
after delivery.

### Eloquent work

- `Customer` has many orders.
- `Order` belongs to a customer and has many order items.
- Add enum casts for status and explicit casts for any stored monetary value.
- Add useful scopes such as paid, shipped, and recent.
- Use an observer only for a true model lifecycle concern. Do not hide the
  domain transition inside an observer.

### Tests to write

- Every allowed transition succeeds.
- Every disallowed transition is rejected with a clear domain exception/result.
- Shipping dispatches `OrderShipped` exactly once.
- Each listener is queued; use `Queue::fake()` and assert the expected jobs.
- Feature tests use `RefreshDatabase` as requested for database-backed flows.

### Do not expect

- Do not let a controller assign an arbitrary status string directly.
- Do not use events as a replacement for the transaction itself; change the
  order state first, then emit the event after success.

## 11. Milestone 5 — Laravel integration checklist

Add these only where a mini-app gives them a real purpose.

| Concept | Place to demonstrate it |
| --- | --- |
| Encapsulation | `Money`, `Percentage`, and order transitions protect invariants |
| Inheritance vs composition | Channel children versus `NotificationSender` owning channels |
| Abstract class vs interface | `Channel` shared behavior versus `PaymentGateway` capability |
| Traits | `HasRetries` only |
| Enums | Currency and order status |
| Service container | Payment bindings and channel collection injection |
| Singleton | A stateless, configured service only if it has a real shared lifetime |
| Contextual binding | A second consumer requiring a deliberately different gateway |
| Providers | Payment and notification registration |
| Facades vs contracts | Compare a Laravel facade use with injected logger/gateway contract |
| Eloquent | Models, relations, scopes, casts, observer |
| Events | `OrderShipped` and three listeners |
| Middleware | A rate-limited/internal request boundary where it makes sense |
| Policies | Customer versus staff authority to view/cancel an order |
| Form Requests | Payment and order validation/authorization |
| DTOs | Charge, notification, and pricing input/output |
| Action classes | Charge payment and transition order |

### Security baseline

- Validate every request through a Form Request.
- Authorize ownership actions through a policy or Form Request authorization.
- Use Laravel's CSRF middleware for browser form requests.
- Rate-limit expensive or sensitive payment endpoints.
- Never log payment tokens, secrets, or full card data.
- Never send an unvalidated amount or currency from a controller straight to a
  gateway.

## 12. Testing strategy

### Test categories

| Test | Use it for | Database? |
| --- | --- | --- |
| Unit | Value objects, strategies, fakes, state rules, actions with fakes | No |
| Feature | Requests, validation, policies, database flows, events | Yes when needed |
| Architecture test (optional) | Guarding conventions such as contract placement | No |

### Rules for valuable tests

- Test observable behavior, not private implementation details.
- Name tests as behavior: `it('rejects a transition from shipped to paid')`.
- Arrange, act, assert—with a blank line between each phase.
- Use an exact expected value rather than duplicating production logic in the
  test to calculate it.
- Use `Http::preventStrayRequests()` whenever an adapter could make HTTP calls.
- Use Laravel fakes for events, queues, mail, notifications, time, and HTTP.
- Use project-owned fakes for your own contracts.
- Test failure paths: validation, declines, invalid state transitions, and
  rounding boundaries are more valuable than a second happy path.

### Test commands

Run a narrow test while building:

```bash
./vendor/bin/sail pest tests/Unit/Concepts/Payment/Actions/ChargePaymentTest.php
```

Run all tests:

```bash
./vendor/bin/sail pest
```

Run Laravel's wrapper if you need its integration options:

```bash
./vendor/bin/sail artisan test --compact
```

## 13. Xdebug: three deliberate debugging exercises

Do not use `dd()` as the primary debugger. Instead, reproduce a failing test or
request, set breakpoints, inspect values, step through the call path, then keep
the regression test after fixing the defect.

### Bug 1: gateway-result interpretation

Create a temporary mismatch between the fake's result and the action's success
check. Put breakpoints in the action and fake. Inspect the actual result object,
the boolean condition, and the returned response.

### Bug 2: pricing rounding

Create a case where a percentage applied to a small amount rounds unexpectedly.
Break in the percentage rule and `Money` operation. Inspect minor units before
and after rounding. Decide and document a single rounding policy.

### Bug 3: illegal state transition

Temporarily allow a disallowed transition such as shipped-to-paid. Break in the
transition action and current state object. Inspect the enum value, target
status, and transition map. Restore the guard and retain the failing-now-passing
test.

### Sail/Xdebug commands

Use Sail's debug command for a CLI workflow when the debugger client is ready:

```bash
./vendor/bin/sail debug test
```

For browser debugging, enable the Xdebug browser trigger in your IDE/browser
extension and set breakpoints before loading the page. Configure the IDE path
mapping from the project root on your Mac to `/var/www/html` in the container.

### What to record in the README

For each bug: symptom, failed test, breakpoint location, observed cause, fix,
and the regression test that prevents recurrence.

## 14. Coverage target

Target at least 80% line coverage, but do not treat the number as the only
quality metric. A well-chosen invalid transition test is better than a shallow
test written only to raise coverage.

Run coverage inside Sail only after Xdebug/PCOV coverage support is configured:

```bash
./vendor/bin/sail pest --coverage
```

Track coverage by area:

- Value objects and enums: near 100%.
- Payment, notification, and pricing decision branches: high coverage.
- Controllers: feature-test successful, invalid, unauthorized, and dependency
  failure responses.
- Order transition map: every allowed and rejected transition.

## 15. Continuous integration

Create a GitHub Actions workflow only after the local suite is stable.

The workflow should:

1. Check out the repository.
2. Install the PHP version declared for CI.
3. Install Composer dependencies with the lock file.
4. Create a safe CI environment file and application key.
5. Start the database service or use the selected test database.
6. Run migrations where feature tests require them.
7. Run Pest.
8. Run the coverage command and fail below 80%.
9. Run Pint formatting checks as a separate quality gate.

Do not put production `.env` values, tokens, or database passwords in the
workflow file. Use GitHub repository secrets when a real secret is required.

## 16. Commit checkpoints

Use small commits that match learning milestones:

```text
chore: set up Laravel Sail and Pest
feat(payment): add gateway contract and fake
feat(payment): bind gateway by environment
test(payment): cover charge validation and failures
feat(notifications): add polymorphic channels
feat(pricing): add pricing rule pipeline
feat(orders): add state transitions and shipped event
test(orders): cover workflow events and queued listeners
ci: run tests and enforce coverage
docs: document architecture and debugging notes
```

Before every commit:

```bash
./vendor/bin/sail pest
```

```bash
vendor/bin/pint --dirty --format agent
```

```bash
git diff --check
```

## 17. Portfolio README outline

Write the README after the implementation exists. Include:

1. A concise project pitch and screenshot/diagram.
2. Local setup and Sail commands.
3. A mini-app table with pattern, Laravel feature, and test location.
4. One short diagram per mini-app showing dependency direction.
5. The three Xdebug bug stories.
6. Test/coverage commands and current coverage result.
7. CI badge after GitHub Actions is configured.
8. A section titled “What I learned” written in your own words.

Avoid claiming this charges real cards or sends real SMS messages unless you
actually implement and secure those integrations.

## 18. Definition of done

The project is complete when all statements below are true:

- The four mini-apps are implemented and independently testable.
- The payment action depends on a contract and resolves the right gateway by
  environment.
- The notification sender contains no channel-specific conditional chain.
- Pricing uses readonly values, a currency enum, strategies, and a pipeline.
- Order transitions reject invalid moves and shipping triggers three queued
  listeners.
- Eloquent relations, scopes, casts, observer, middleware, policy, Form
  Requests, DTOs, and action classes each have a meaningful example.
- Unit tests use fakes and avoid a database where possible.
- Feature tests use the database reset trait where persistence is tested.
- You have investigated and documented three bugs with Xdebug.
- Coverage is above 80% and CI enforces it.
- You can explain why each abstraction exists and what would break if it were
  removed.
