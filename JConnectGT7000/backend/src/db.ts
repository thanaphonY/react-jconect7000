import { Pool } from 'pg';

export interface QueryResult {
  rows: unknown[];
  rowCount?: number | null;
}

export interface Queryable {
  query(queryText: string, values?: unknown[]): Promise<QueryResult>;
}

export function createPool(connectionString: string): Pool {
  return new Pool({
    connectionString,
    application_name: 'jconnect-backend',
    connectionTimeoutMillis: 5000,
  });
}
