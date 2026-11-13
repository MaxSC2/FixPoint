# UI-06 — Form resilience and validation retry report

**Branch:** `feature/ui-06-form-resilience`  
**Target:** `develop`  
**Status:** implementation complete for the two scoped retry defects; full UI-06 integration QA remains open.

## Defects fixed

### Customer request comments
Previously, a server-side validation redirect returned the request detail page but the customer's submitted comment body was not restored in the textarea. The view now normalizes the `old` and `errors` view data and renders `old.body` back into the field using the shared HTML escaping helper.

The customer comment list continues to filter out comments marked `is_internal`. Internal technician notes are not rendered in the customer workspace. The reject-estimate submitter also carries a confirmation message; the JavaScript enhancement can cancel the submit, while the original POST + CSRF form remains the functional fallback.

### Technician diagnosis and estimate
Previously, a validation redirect could repopulate the work card from the persisted request model and discard the technician's unsaved form values. The view now prefers submitted old input for:
- `diagnostic_result`
- `estimated_cost`
- `estimated_ready_at`

Persisted values remain the fallback when the page is opened normally. The view does not save data; persistence and validation remain backend responsibilities.

## Regression coverage

Added two ViewRenderer-based tests in `tests/Backend/UiTemplateContractTest.php`:
1. Render the customer request detail with a failed comment submission and verify escaped old input is restored, the field error is associated, and an internal note is absent.
2. Render the technician work card with a failed diagnosis submission and verify the unsaved diagnosis, estimate and ready timestamp are restored instead of the older persisted values.
3. Assert that the estimate-rejection button is guarded by the shared progressive confirmation handler.

## CI evidence

[GitHub Actions run #43389133646](https://github.com/MaxSC2/FixPoint/actions/runs/43389133646) completed successfully on commit `0990f51167fe4f444a13bbec2b22abad8a61d15e`.

- PHP lint: **121 files OK**
- Unit and integration tests: **135 passed, 0 failed**
- Assertions: **937**
- Docker Compose runtime: configuration, image build, app/MySQL startup, health endpoint, public-route smoke and cleanup all passed.

The later documentation-only commits are checked again by the final PR head workflow.

## Changed files

- `app/Views/pages/customer/requests/show.php`
- `app/Views/pages/technician/requests/show.php`
- `tests/Backend/UiTemplateContractTest.php`
- `docs/13-CHATGPT-IMPLEMENTATION-MAP.md`
- `docs/17-UI-01-QA-CHECKLIST.md`

## Remaining QA gates

- Browser visual inspection and screenshots at desktop, tablet and mobile breakpoints are not claimed as complete.
- The final technician backend routes and mutations must be verified against the backend TASK-004 implementation once it is integrated. These changes only preserve the view state supplied by the existing session/renderer contract.
- Run the full customer → admin → technician → customer journey after the technician workflow is available on the integration branch.
