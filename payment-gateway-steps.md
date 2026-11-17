# Payment Gateway Mini-App — Step-by-Step Learning Guide

Build this mini-app in order. Do not skip ahead: each step introduces one idea,
then proves it with a small test or application check.

This guide deliberately does **not** contain the complete implementation. When
you reach a step, write the code yourself and ask for a line-by-line review or
the next file.

## What you are building

A JSON endpoint that accepts a payment request and returns either:

- a successful payment identifier; or
- a clear declined-payment response.

The controller must not know whether the payment provider is Stripe, a fake, or
another provider. It depends only on the application's `PaymentGateway`
contract.

## Finished design

```text
HTTP request
    ↓
ChargePaymentRequest validates input
    ↓
PaymentController turns input into ChargeData
    ↓
ChargePayment action depends on PaymentGateway
    ↓
PaymentServiceProvider chooses an implementation
    ├── FakeGateway in local/testing
    └── StripeGateway in production
```

## Concepts you will demonstrate

| Concept | Where you show it |
| --- | --- |
| Interface | `PaymentGateway` |
| Dependency inversion | `ChargePayment` depends on the interface |
| Liskov substitution | Fake and Stripe both work wherever the contract is required |
| Dependency injection | Laravel provides the action and gateway |
| Service container | Provider binds contract to implementation |
| Provider | `PaymentServiceProvider` |
| Form Request | `ChargePaymentRequest` |
| DTO | `ChargeData` and `PaymentResult` |
| Action class | `ChargePayment` |
| Unit test with fake | action and fake tests |
| Feature test | controller request/response tests |

## Before you begin

Start Sail and verify tests are green:

```bash
./vendor/bin/sail up -d
```

```bash
./vendor/bin/sail pest
```

Expected: all existing tests pass. If they do not, fix the baseline before
adding payment code.

---

## Step 1 — Decide the payment language

Before coding, agree on the words the application owns. The first version
supports one currency (`usd`), a positive amount stored in cents, and a payment
method token. It returns either success with a transaction ID or failure with a
human-readable reason.

### Why this comes first

Stripe's language should not leak everywhere. Your application needs a stable
language even if Stripe is replaced later.

### Decisions to write down

- Amounts are integer cents. `1500` means $15.00.
- Currency is lowercase `usd` for this mini-app.
- A token represents a payment method; it is not a card number.
- A successful payment has a transaction ID.
- A failed payment has a failure reason.

### What not to do

- Do not use floats such as `15.50` for money.
- Do not collect or store real card numbers.
- Do not add a real Stripe key to `.env` or source control.

---

## Step 2 — Create the contract

File:

```text
app/Concepts/Payment/Contracts/PaymentGateway.php
```

Turn the generated class into an interface. Give it one method called `charge`.
That method accepts a `ChargeData` object and returns a `PaymentResult` object.

### Why

An interface describes a capability without choosing an implementation. The
action will ask for a thing that can charge—not specifically a Stripe class.

### Expected result

The interface contains no payment logic and no Stripe references.

### Checkpoint question

If you added a PayPal gateway later, would `ChargePayment` need to change?
The answer should be no.

---

## Step 3 — Create immutable DTOs

Files:

```text
app/Concepts/Payment/DTOs/ChargeData.php
app/Concepts/Payment/DTOs/PaymentResult.php
```

### `ChargeData`

Make this a final readonly value object. It should hold:

- amount in cents;
- currency;
- payment method token.

Reject clearly invalid direct use, such as a zero/negative amount or empty
token. HTTP validation still belongs in the Form Request later; this guard
protects calls that do not originate in HTTP.

### `PaymentResult`

Make this a final readonly value object. It should represent either:

- success: a transaction ID, with no failure reason; or
- failure: a reason, with no transaction ID.

Use named construction methods such as a success factory and a failure factory,
instead of allowing arbitrary public construction.

### Why

DTOs make the data crossing a boundary explicit and typed. `readonly` prevents
a caller from changing the amount after it was handed to a gateway.

### Expected result

The controller, action, fake, and Stripe adapter all exchange the same two
types. No component passes a loose payment array.

### What not to do

- Do not inject `Request` into DTOs.
- Do not return an unstructured array from the gateway.
- Do not make money mutable.

---

## Step 4 — Write the fake gateway first

File:

```text
app/Concepts/Payment/Gateways/FakeGateway.php
```

Make this class implement `PaymentGateway`.

Its behavior should be deterministic:

1. Record every received `ChargeData` object in an internal list.
2. Return a predictable transaction ID for normal charges.
3. Provide one small public method that makes the *next* charge fail with a
   chosen reason.
4. Provide a read-only way for tests to inspect recorded charges.

### Why

This fake replaces an external payment service in unit and feature tests. It is
not a mock framework object: it is a real, small implementation of your own
contract.

### Expected result

Your tests can prove exactly what was charged and simulate a decline without a
database, API key, or network request.

### What not to do

- Do not call Stripe or Laravel's HTTP client here.
- Do not use random transaction IDs; predictable values make tests clearer.
- Do not put test-only logic in the production action.

---

## Step 5 — Test the fake gateway

File:

```text
tests/Unit/Concepts/Payment/Gateways/FakeGatewayTest.php
```

Write Pest unit tests for these behaviors:

1. Two charges produce predictable, different transaction IDs.
2. Both charge inputs are recorded.
3. A configured failure affects the next charge only.
4. The charge after a configured failure succeeds normally.

### Why now

The fake becomes part of your testing infrastructure. If it is unreliable,
every later payment test becomes unreliable too.

### Run this checkpoint

```bash
./vendor/bin/sail pest tests/Unit/Concepts/Payment/Gateways/FakeGatewayTest.php
```

Expected: tests pass without booting Laravel, connecting to MySQL, or calling
the network.

---

## Step 6 — Create the application action

File:

```text
app/Concepts/Payment/Actions/ChargePayment.php
```

Give this class a constructor dependency on `PaymentGateway`. Add one public
method, commonly named `handle`, that accepts `ChargeData` and returns
`PaymentResult`.

For the first version, the action delegates charging to its injected gateway.
Later, it becomes the focused place for payment-related business workflow, such
as creating a payment record or dispatching a successful-charge event.

### Why

The action represents the use case “charge a payment.” It is neither an HTTP
controller nor a Stripe adapter. This is the layer that benefits from depending
on the contract.

### What not to do

- Do not instantiate `StripeGateway` inside the action.
- Do not call `app()` or `resolve()` inside the action.
- Do not put HTTP response logic here.

---

## Step 7 — Test the action with the fake

File:

```text
tests/Unit/Concepts/Payment/Actions/ChargePaymentTest.php
```

Write two unit tests:

1. A successful fake charge returns the expected successful result.
2. A configured fake decline returns the expected failed result.

In both tests, construct the fake and the action directly. Assert that the fake
received exactly one charge and that it received the same DTO you passed to the
action.

### Why

This proves dependency inversion in a test: the action has no need to boot
Laravel, use a provider, or know about Stripe.

### Run this checkpoint

```bash
./vendor/bin/sail pest tests/Unit/Concepts/Payment/Actions/ChargePaymentTest.php
```

Expected: a fast test with no database usage.

---

## Step 8 — Bind the contract in a provider

File:

```text
app/Providers/PaymentServiceProvider.php
```

In the provider's `register` method, add a container binding for
`PaymentGateway`.

- In `production`, resolve `StripeGateway`.
- In `local` and `testing`, resolve `FakeGateway`.

Keep this choice in the provider. Do not add environment checks to the
controller or action.

### Why

Laravel can automatically construct concrete classes, but it cannot guess which
implementation an interface should use. The provider gives it that answer in
one central location.

### Expected result

When Laravel resolves `ChargePayment`, it automatically receives the correct
gateway for the current environment.

### Checkpoint question

Where would you change the application from Stripe to another provider? The
answer should be the provider binding, not every controller.

---

## Step 9 — Add Stripe configuration and adapter

Files:

```text
app/Concepts/Payment/Gateways/StripeGateway.php
config/services.php
.env.example
```

### Configuration

Add a `stripe` section to `config/services.php` containing a secret and base
URL read from environment values. Add empty/documented matching variables to
`.env.example`.

### Adapter behavior

Make `StripeGateway` implement `PaymentGateway`. It should:

1. Read its secret and base URL from configuration.
2. Reject a missing secret with a clear configuration exception.
3. Send the charge data to Stripe's payment-intent endpoint.
4. Apply a short timeout.
5. Translate Stripe's response into `PaymentResult`.

### Why

This is an adapter. Stripe-specific URLs, authentication, request fields, and
response fields belong here and nowhere else.

### What not to do

- Do not read `env()` directly in the gateway class.
- Do not put a Stripe secret in a test or commit it.
- Do not allow a real HTTP request during a test.

---

## Step 10 — Test the Stripe adapter safely

Create a Pest feature test under:

```text
tests/Feature/Concepts/Payment/Gateways/StripeGatewayTest.php
```

Use Laravel's HTTP fake. Test:

1. A successful fake Stripe response becomes a successful `PaymentResult`.
2. A declined fake Stripe response becomes a failed `PaymentResult`.
3. The adapter made a POST request to the expected payment-intent URL.
4. Unexpected network calls fail the test immediately.

### Why this is a feature test

The Laravel HTTP client and configuration system are framework features, so the
application must boot. The request still never leaves your computer.

### Run this checkpoint

```bash
./vendor/bin/sail pest tests/Feature/Concepts/Payment/Gateways/StripeGatewayTest.php
```

---

## Step 11 — Add request validation

File:

```text
app/Http/Requests/ChargePaymentRequest.php
```

Set authorization to allow this temporary public demonstration endpoint. Add
rules that require:

- integer amount, greater than or equal to your chosen minimum cents;
- supported currency (`usd` for now);
- non-empty payment-method token with a sensible maximum length.

### Why

A Form Request keeps validation out of the controller. Laravel runs it before
the controller action, so invalid input cannot reach your domain classes.

### What not to do

- Do not validate directly inside `PaymentController`.
- Do not claim that validation is authorization; ownership/roles arrive with
  the order and policy milestone.

---

## Step 12 — Add the controller

File:

```text
app/Http/Controllers/PaymentController.php
```

Create a `store` method that receives:

- the validated `ChargePaymentRequest`;
- the `ChargePayment` action from Laravel's container.

The method should:

1. Convert validated fields to `ChargeData`.
2. Call the action exactly once.
3. Return JSON with payment ID/status for success.
4. Return JSON with the decline reason for failure.

Choose and document response statuses:

| Situation | Status |
| --- | --- |
| Valid, approved payment | `201 Created` |
| Validation error | Laravel-generated `422 Unprocessable Content` |
| Gateway decline | `402 Payment Required` for this lab |

### Why

The controller is a thin HTTP adapter. Its only job is transforming the request
to a DTO and a domain result to JSON.

---

## Step 13 — Register the route

File:

```text
routes/web.php
```

Add one named POST route for the controller's `store` method. Name it
`payments.charge`.

### Why

Named routes stop tests and future forms from relying on a copied URL string.

### Note

Because this is initially a `web.php` route, a real browser form needs a CSRF
token. Your JSON feature tests can use Laravel's testing helpers.

---

## Step 14 — Add controller feature tests

File:

```text
tests/Feature/PaymentControllerTest.php
```

Write three feature tests:

1. Valid input returns `201` and the expected success JSON.
2. Invalid amount, currency, and token return `422` with errors for all three
   fields.
3. A configured fake decline returns `402` and its failure message.

For success/decline tests, replace the container's `PaymentGateway` binding in
that individual test with a `FakeGateway` instance. This lets the test inspect
the received charge and control the response.

### Why

These tests prove the public contract of the application: request validation,
dependency injection, status code, and response body.

### Run this checkpoint

```bash
./vendor/bin/sail pest tests/Feature/PaymentControllerTest.php
```

---

## Step 15 — Complete verification

Run the focused suite first:

```bash
./vendor/bin/sail pest tests/Unit/Concepts/Payment
```

```bash
./vendor/bin/sail pest tests/Feature/PaymentControllerTest.php
```

```bash
./vendor/bin/sail pest tests/Feature/Concepts/Payment/Gateways/StripeGatewayTest.php
```

Then run all tests and format PHP:

```bash
./vendor/bin/sail pest
```

```bash
vendor/bin/pint --dirty --format agent
```

Confirm the route exists:

```bash
./vendor/bin/sail artisan route:list --name=payments.charge
```

## Step 16 — Manual local check

Send a local request using the fake gateway:

```bash
curl -X POST http://localhost:8080/payments/charge \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{"amount":1500,"currency":"usd","payment_method_token":"pm_test_visa"}'
```

Expected: a successful JSON response with a deterministic fake payment ID.

Try invalid input next: zero amount, unsupported currency, and empty token.
Expected: a `422` JSON response listing validation errors.

## Step 17 — Commit checkpoint

Only after all tests pass:

```bash
git status --short
```

Review the changed files. Ensure `.env` is not listed, then commit:

```bash
git add app config/services.php routes/web.php tests .env.example
```

```bash
git commit -m "feat(payment): add environment-aware payment gateway"
```

## Completion checklist

- [ ] `PaymentGateway` is an interface.
- [ ] `ChargePayment` depends only on that interface.
- [ ] DTOs are readonly and represent valid payment data/results.
- [ ] `FakeGateway` has no network or database dependency.
- [ ] `StripeGateway` contains all Stripe-specific details.
- [ ] The provider selects fake locally/testing and Stripe in production.
- [ ] The controller uses a Form Request and remains thin.
- [ ] Unit tests pass without Laravel, MySQL, or HTTP.
- [ ] Feature tests cover success, validation failure, and gateway decline.
- [ ] Stripe tests fake every HTTP request.
- [ ] All tests and Pint pass.
