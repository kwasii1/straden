# Changelog

## [1.3.0](https://github.com/kwasii1/straden/compare/straden-v1.2.0...straden-v1.3.0) (2026-10-01)


### Features

* implement logging for connection failures across services; add … ([dd6748b](https://github.com/kwasii1/straden/commit/dd6748b10147054f8daa6063406a88dfd6beb14f))
* implement logging for connection failures across services; add LogsConnectionFailures trait ([a59b375](https://github.com/kwasii1/straden/commit/a59b375868a8237c5a7ff2b8cdeedad6b0a0a046))

## [1.2.0](https://github.com/kwasii1/straden/compare/straden-v1.1.0...straden-v1.2.0) (2026-10-01)


### Features

* add connectionHost method to Connector model and update service classes to use it; enhance tests for loopback host behavior and project-specific connector deletion ([19de552](https://github.com/kwasii1/straden/commit/19de55222a19c58214682c901a21a61d7bc1e4f8))
* add connectionHost method to Connector model and update service… ([44aaff9](https://github.com/kwasii1/straden/commit/44aaff90e01d817e192f4161db8848e50a424627))

## [1.1.0](https://github.com/kwasii1/straden/compare/straden-v1.0.0...straden-v1.1.0) (2026-10-01)


### Features

* enhance connector management with edit and delete confirmation, improve logging, and update Docker configuration ([77af9bd](https://github.com/kwasii1/straden/commit/77af9bdfe3fed1ad47064de02e4a39c550a68c8f))
* enhance connector management with edit and delete confirmation,… ([3fe5ca8](https://github.com/kwasii1/straden/commit/3fe5ca8da8ab32f356edcc086f57e10ae94c5709))

## 1.0.0 (2026-09-25)


### Features

* add command to download provider logos from GitHub ([ef0700e](https://github.com/kwasii1/straden/commit/ef0700e92b3b719b759e325594bc396520dd152c))
* add connectors management with InfluxDB integration ([c55f754](https://github.com/kwasii1/straden/commit/c55f75490c80c0f2c7bc2b6c84c64c32e32967fc))
* add contributing guidelines, security policy, and initial documentation ([0a7c7b5](https://github.com/kwasii1/straden/commit/0a7c7b57e74afe241b9a5a9923478385933dfd9d))
* add export functionality for completed insight reports as markdown ([352f30c](https://github.com/kwasii1/straden/commit/352f30c7d373c30ef1c52ffab67abf88cf21cd75))
* add Horizon and Log Viewer links to sidebar navigation ([32fc927](https://github.com/kwasii1/straden/commit/32fc92716c6859143f882cac2244e256d8c4962d))
* Add InfluxDB metrics retrieval and visualization for run performance; enhance view-run page with time-series charts ([688a966](https://github.com/kwasii1/straden/commit/688a966629b66b03f6eb2544140fa71c70ece45f))
* Add JSON scripts for status distribution and performance trend; update chart data retrieval in overview page ([bcca987](https://github.com/kwasii1/straden/commit/bcca9878084f67e04db8903082ef67ed6d08305c))
* add k6 installation step in CI workflow ([9a28a30](https://github.com/kwasii1/straden/commit/9a28a307628dafbb99249c2aea5479ca82ecfa32))
* add lifecycle timeout validation and enhance error handling for teardown in script execution ([5a340fc](https://github.com/kwasii1/straden/commit/5a340fc9bb797ae12e7a5d6d71458a5a1204ed17))
* Add listBranches method to Git provider classes and update repository shape to include provider_id ([1cb2e1b](https://github.com/kwasii1/straden/commit/1cb2e1bddacc47980a382aa7fee24568cd7646e8))
* add MCP tools for script management and API token handling ([55942bf](https://github.com/kwasii1/straden/commit/55942bf66e82c7f13ff3337ec7b120f29e89f228))
* add PHP configuration file and self-hosting documentation ([92f953e](https://github.com/kwasii1/straden/commit/92f953eb5bc760d5c63265bd6fd12c836a3df803))
* Add provider and model selection to AgentChat and ScriptAgentChat components, enhance conversation management, and implement AvailableModelMap for dynamic model retrieval ([31fcf7d](https://github.com/kwasii1/straden/commit/31fcf7d0b302d6faf478ba80815189ef88e3506a))
* Add response code metrics and visualization to dashboard, including breakdown and time series data ([88a6c60](https://github.com/kwasii1/straden/commit/88a6c60bfb7ae053b270cec2da520cd3b898069f))
* add RunInfluxMetricsTool for fetching performance metrics from InfluxDB ([1ff581d](https://github.com/kwasii1/straden/commit/1ff581d6a603eff53dc1f633278e985a2b4fa15f))
* add ScriptAgent and TestAgent classes for AI functionality; enhance view-test with AI script generation modal ([8cbe7ac](https://github.com/kwasii1/straden/commit/8cbe7ac7b80970b4404c1f361323cda74ac157af))
* add stats computation for connected AI providers and enhance UI for provider management ([eda6141](https://github.com/kwasii1/straden/commit/eda6141dd301032cf7de2cc7911b566fe2a8a757))
* Add UpdateScriptTool for modifying existing k6 scripts; implement RunTestJob for executing tests and queuing runs; enhance TestAgent with new script handling instructions ([3d4de55](https://github.com/kwasii1/straden/commit/3d4de55807ba98d84d04d94d6da44afcb2fbcfd5))
* add WriteScriptFileTool for file management in script directory ([6b21db9](https://github.com/kwasii1/straden/commit/6b21db9b798895f7b6a39dedb87757a9e44a67e0))
* **charts:** add various chart components for monitoring performance metrics ([9db7b09](https://github.com/kwasii1/straden/commit/9db7b09db2b6aec4cbe0ec2dc9ac5c5754786245))
* create setup and user management pages ([92f953e](https://github.com/kwasii1/straden/commit/92f953eb5bc760d5c63265bd6fd12c836a3df803))
* enhance agent behavior to respond conversationally to casual remarks and persist provider/model selection across page refreshes ([1df95c7](https://github.com/kwasii1/straden/commit/1df95c7e6ab83933fcb1324d8ad6f1ed47e1db5b))
* enhance AppServiceProvider with DevCommands configuration and add Opencode service credentials ([6440e45](https://github.com/kwasii1/straden/commit/6440e45d4ae0c64bf3dcbd253b7913783e97b6fd))
* enhance chat message persistence with conversation reuse and optimistic UI updates ([883a11b](https://github.com/kwasii1/straden/commit/883a11b0290009a77ef63eabc6b0c82896ec8b1a))
* enhance dashboard view and add loading state component ([2139187](https://github.com/kwasii1/straden/commit/21391873c746ba7efe136fef79a01b4c8ccc96ae))
* enhance InfluxDB metrics tools with per-endpoint breakdown and update view-run page for endpoint filtering ([5418ea8](https://github.com/kwasii1/straden/commit/5418ea84d247ae80cfa7a3114156e9fc50e7d9ae))
* enhance k6 command logging and error handling, add notification filtering ([23660d1](https://github.com/kwasii1/straden/commit/23660d19fcbe6177a10a0f5690d55ed5d26b8347))
* enhance k6 command to include p(99) in summary trend stats and improve threshold evaluation ([57d8313](https://github.com/kwasii1/straden/commit/57d83133d79c1e6827f22100dc00947cc8877e94))
* enhance project management dashboard with search and edit functionality ([eb23fea](https://github.com/kwasii1/straden/commit/eb23fea8b6a827b65588d49dd45e38b8c4fbe03b))
* enhance project overview with run statistics and charts ([d809220](https://github.com/kwasii1/straden/commit/d809220ed4d16394abcd439f2850e64180121eb1))
* Enhance repository synchronization with user notifications and event broadcasting ([0b70795](https://github.com/kwasii1/straden/commit/0b707951d74336f9747bdf1ec63d7cc95bfcef80))
* Enhance run management with slug generation, add view-run page, and improve RunTable with project filtering ([ec2bb38](https://github.com/kwasii1/straden/commit/ec2bb3885bec6f5578d76460b196fb8ff387417a))
* Enhance ScriptAgentChat and view-test-script components with improved UI, dynamic tool handling, and conversation management ([47c36dd](https://github.com/kwasii1/straden/commit/47c36dddef7a1737cda78f32717b48bcc9321800))
* implement AI insights model selection and management in settings and run panel ([833ed4b](https://github.com/kwasii1/straden/commit/833ed4bb9f3b700a7ea9ac7035c9ddb453ed95f7))
* implement AI provider credential management with encryption and runtime configuration ([f9c2a0a](https://github.com/kwasii1/straden/commit/f9c2a0a3d37f9b385b2fda0acce38bf3d33a1bf1))
* Implement AI Test Assistant with conversation handling and script management ([df9ba8d](https://github.com/kwasii1/straden/commit/df9ba8dab7b417dcbd4c5d1639e57def75cdd980))
* implement connector management with multi-select functionality in Livewire component ([068b1b2](https://github.com/kwasii1/straden/commit/068b1b2b75d30a1ee1ee74cb4112b6d56cabc54a))
* implement endpoint grouping and normalization for dynamic routes in InfluxDbService ([8f3c6eb](https://github.com/kwasii1/straden/commit/8f3c6eb5c05d4b3ab663329c188cd372c9fb5e28))
* implement file icon component and multi-combobox component ([92f953e](https://github.com/kwasii1/straden/commit/92f953eb5bc760d5c63265bd6fd12c836a3df803))
* Implement file saving functionality in the code editor with path validation and update UI for editable state ([01cf7d1](https://github.com/kwasii1/straden/commit/01cf7d189472990783c790953413956c9442fcd0))
* Implement Git provider integrations for Azure DevOps, Bitbucket, GitHub, and GitLab ([04a1824](https://github.com/kwasii1/straden/commit/04a1824e29d9ce85b7e91acb770ee0adaca70c6e))
* implement log persistence for run executions, enhance run progress tracking and UI components ([748bdcd](https://github.com/kwasii1/straden/commit/748bdcdf2a287a49dae349819cc5260bdcb1ed5f))
* Implement notification system for run insights and script generation ([2e5348b](https://github.com/kwasii1/straden/commit/2e5348b6369855b37d0304a34ccfb729dfb5bca0))
* implement pagination, search, and sorting for runs and tests in dashboard views ([b215669](https://github.com/kwasii1/straden/commit/b21566915ff662ef7b2f5edd16e41d5948350645))
* implement run cancellation and process management; add RunResultService for handling k6 execution results ([7d0bdd9](https://github.com/kwasii1/straden/commit/7d0bdd9ab61984b7db74c64e7e2f48cb85657beb))
* Implement script management features in dashboard ([f3177e6](https://github.com/kwasii1/straden/commit/f3177e649717bb479a9471cea704246959e0f7c3))
* implement update functionality for test details and connectors in Livewire component ([980fc15](https://github.com/kwasii1/straden/commit/980fc15af67107748bd8d68bd444afbef07d0f84))
* **influx:** enhance metrics queries with time range filtering for runs ([d86fd7a](https://github.com/kwasii1/straden/commit/d86fd7a3d09c0d1f4afcf8679069153a48c01de8))
* integrate Laravel Echo with Reverb and Pusher for real-time broadcasting ([7704a28](https://github.com/kwasii1/straden/commit/7704a28ca3b2f802c8afe118db2b14c7ee495ce2))
* migrate agent conversation messages to new steps format and update related models and tests ([7dcf024](https://github.com/kwasii1/straden/commit/7dcf024ece7065712900c8b9e00f89f77e4c2711))
* Refactor run metrics handling in view-run component for improved performance and clarity ([1a85445](https://github.com/kwasii1/straden/commit/1a854450a201c7e923c6ad123a13895e1d0809d9))
* refactor RunTerminal to stream log chunks and update Run model on creation ([746dfbd](https://github.com/kwasii1/straden/commit/746dfbd893d3841f388d04823c94d556dd90e81d))
* Remove design_spec.md as part of project restructuring ([104952c](https://github.com/kwasii1/straden/commit/104952caa8cf74e05b042754b68766ff1e9d2a45))
* update AvailableModelMap with new model and enhance script-agent-chat UI ([75aa106](https://github.com/kwasii1/straden/commit/75aa106574ab213170667e17e66968ea1e51eee3))
* Update font to 'Inter' in app.css and vite.config.js for improved typography ([9edb819](https://github.com/kwasii1/straden/commit/9edb819d336f565645099c089325fe6329d7f839))
* update k6 installation method in CI workflow to use GitHub releases ([472190d](https://github.com/kwasii1/straden/commit/472190d1b6f5592b3ac8ff25886f3c5e7c09c2f4))
* update notification delivery to use only database channel and enhance message display ([7d75dee](https://github.com/kwasii1/straden/commit/7d75deeb8cfa8eddd8b7ac840014259d2fa563ea))
* update PHP version requirements and improve test assertions for clarity ([82e365a](https://github.com/kwasii1/straden/commit/82e365a3b3ae8baad7571df07fd333fa7933760e))
* update repository picker and git providers UI for improved usability and visual consistency ([83f6bfc](https://github.com/kwasii1/straden/commit/83f6bfcef27f191e55e6d724e7ed70905c03b04e))
* update UI references to "Straden Agent" and enhance notification bell design ([16d77db](https://github.com/kwasii1/straden/commit/16d77db532488961e9db4dfda5bab6e3332efbdf))


### Bug Fixes

* initialize description property in new-test and view-test components ([97b38bb](https://github.com/kwasii1/straden/commit/97b38bb736d4113279385779cbee9a201604bd1b))

## Changelog

All notable changes to Straden are documented in this file.

This project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html). Release entries are generated automatically by [release-please](https://github.com/googleapis/release-please) from [Conventional Commits](https://www.conventionalcommits.org) — don't edit them by hand.

## Initial development (pre-release)

Summary of what was built before the first tagged release.

### Added

- Browser IDE for k6 scripts with a file explorer, multi-file scripts, uploads, validation and VS Code-style autosave.
- One-click k6 runs with live progress, logs and metrics over websockets, and persisted run history.
- InfluxDB-backed run metrics: latency percentiles, throughput, error rates, response codes, per-endpoint breakdowns and HTTP timing.
- AI test agent for planning and writing scripts, and AI run insight reports, with support for many model providers.
- Connectors for Prometheus, PostgreSQL, MySQL, Redis, MongoDB and Grafana.
- Git repository integration; repositories can be linked to individual tests to scope what the AI agents read.
- MCP server with Sanctum API tokens (`mcp:read`, `mcp:write`, `mcp:run` abilities) for coding agents.
- First-run setup wizard, headless admin bootstrap (`STRADEN_ADMIN_*`) and `straden:admin` recovery command.
- Admin-managed user accounts; instance settings, Horizon and the log viewer are restricted to admins.
- Docker image (FrankenPHP, k6 v2.0.0) and Compose stack with PostgreSQL, Redis and InfluxDB 1.8, automatic HTTPS and same-origin websockets.
- Multi-architecture image publishing to GitHub Container Registry.

### Changed

- Public registration is disabled; admins create user accounts.

### Fixed

- Cancelled runs are recorded as `aborted` instead of `error` (k6 exit code 105).
- New scripts read the target from `__ENV.TARGET_URL` instead of an unreplaced placeholder.
- MongoDB connector connection tests no longer always fail.
