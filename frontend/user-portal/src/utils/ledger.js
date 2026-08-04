/**
 * Route for a ledger row's detail page. Rows carry `source` + `record_id`;
 * anything without them predates the unified ledger and can only be a
 * `transactions` row.
 */
export function ledgerDetailPath(row) {
  if (!row) return null
  if (row.source && row.record_id) return `/transactions/${row.source}/${row.record_id}`
  return row.trans_id ? `/transactions/${row.trans_id}` : null
}
