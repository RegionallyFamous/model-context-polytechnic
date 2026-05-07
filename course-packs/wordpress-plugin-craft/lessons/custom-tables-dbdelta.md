Custom tables are appropriate when WordPress built-ins do not match the query or volume needs. Use dbDelta carefully, include primary keys and useful indexes, and store a schema version option so migrations are repeatable. Use $wpdb->prepare for dynamic SQL values. Source: https://developer.wordpress.org/plugins/creating-tables-with-plugins/

Common failure: custom tables without schema versioning or indexes. They work in demos and become expensive in production.
