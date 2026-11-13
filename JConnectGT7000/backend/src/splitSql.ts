export function splitSqlStatements(sql: string): string[] {
  const statements: string[] = [];
  let current = '';
  let index = 0;
  let dollarTag: string | null = null;

  while (index < sql.length) {
    const char = sql[index];

    if (dollarTag !== null) {
      if (sql.startsWith(dollarTag, index)) {
        current += dollarTag;
        index += dollarTag.length;
        dollarTag = null;
        continue;
      }
      current += char;
      index += 1;
      continue;
    }

    if (char === '-' && sql[index + 1] === '-') {
      const end = sql.indexOf('\n', index);
      index = end === -1 ? sql.length : end + 1;
      continue;
    }

    if (char === '/' && sql[index + 1] === '*') {
      const end = sql.indexOf('*/', index + 2);
      if (end === -1) {
        throw new Error('Unterminated block comment in migration SQL');
      }
      index = end + 2;
      continue;
    }

    if (char === "'" || char === '"') {
      const quote = char;
      current += char;
      index += 1;
      while (index < sql.length) {
        const quoted = sql[index];
        current += quoted;
        if (quoted === quote) {
          if (sql[index + 1] === quote) {
            current += sql[index + 1];
            index += 2;
            continue;
          }
          index += 1;
          break;
        }
        index += 1;
      }
      continue;
    }

    if (char === '$') {
      const match = /^\$[A-Za-z0-9_]*\$/.exec(sql.slice(index));
      if (match) {
        dollarTag = match[0];
        current += dollarTag;
        index += dollarTag.length;
        continue;
      }
    }

    if (char === ';') {
      const statement = current.trim();
      if (statement.length > 0) {
        statements.push(statement);
      }
      current = '';
      index += 1;
      continue;
    }

    current += char;
    index += 1;
  }

  const tail = current.trim();
  if (tail.length > 0) {
    statements.push(tail);
  }

  return statements;
}
