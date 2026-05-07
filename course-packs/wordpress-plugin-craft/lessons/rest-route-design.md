WordPress REST endpoints should be boring to call and boring to review: namespace, route, methods, args, permission_callback, callback, and response shape. Public reads and private writes should be visibly different. Use schemas to guide clients and validation. Source: https://developer.wordpress.org/rest-api/

For agent-facing tools, treat schemas as part of the user experience. The better the schema, the fewer strange model calls.
