# RFC 9535 compliance fixtures

Unmodified data from [jsonpath-standard/jsonpath-compliance-test-suite](https://github.com/jsonpath-standard/jsonpath-compliance-test-suite), pinned to commit [`20ebdcce5008c8a777ee9c80ad853ecf606950b5`](https://github.com/jsonpath-standard/jsonpath-compliance-test-suite/commit/20ebdcce5008c8a777ee9c80ad853ecf606950b5).

The suite contains 715 cases. Tests check values and normalized paths together, accept only explicitly enumerated alternative orderings, and require invalid queries to be rejected before evaluation on any document. Objects and arrays remain distinct. Nothing is fetched over the network during tests.

`cts.json` is distributed under the upstream BSD-2 license. See LICENSE and NOTICE in this directory. Update the data and commit reference together when adopting an upstream revision.
