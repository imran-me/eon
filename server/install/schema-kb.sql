-- EON's ERP knowledge corpus and question bank.
-- Run once, on the same database as schema.sql:
--   mysql -u u239665931_eon -p u239665931_eon < server/install/schema-kb.sql
--
-- Two tables, both loaded by tools/kb-import.php from tools/corpus/*.jsonl.
-- They are read-only at runtime: the retrieval layer only ever SELECTs.
--
-- Sizing, MEASURED on MariaDB 10.4 with the corpus as generated 2026-08-24
-- (101,483 knowledge records, 188,885 questions), not estimated:
--
--   eon_kb             159 MB data + 39 MB index = 198 MB
--   eon_kb_questions    35 MB data + 44 MB index =  78 MB
--   ----------------------------------------------------------
--   total                                          ~276 MB
--
-- The JSONL on disk is 127 MB; the tables are larger because utf8mb4 stores
-- Bangla at three bytes a character and every index keeps its own copy of
-- what it covers. Check the hosting plan's database quota before loading —
-- a half-loaded table is worse than an empty one.
--
-- If 276 MB is too much, load a subset rather than nothing:
--   php tools/kb-import.php --lang en                 English only  (~140 MB)
--   php tools/kb-import.php --vertical travel,hospital,university   the trades you sell to
--   php tools/kb-import.php --only q                  the question bank alone (78 MB)
-- The corpus regenerates from tools/kb/ in seconds, so nothing is lost by
-- loading narrow first and widening later.

CREATE TABLE IF NOT EXISTS eon_kb (
  id            VARCHAR(190) PRIMARY KEY,
  facet         VARCHAR(32)  NOT NULL,
  lang          VARCHAR(4)   NOT NULL,
  vertical      VARCHAR(32)  NULL,
  module        VARCHAR(40)  NULL,
  subject       VARCHAR(190) NULL,
  title         VARCHAR(400) NOT NULL,
  body          MEDIUMTEXT   NOT NULL,
  tags          VARCHAR(255) NULL,
  meta          JSON         NULL,
  KEY idx_scope    (vertical, module, lang),
  KEY idx_facet    (facet, lang),
  KEY idx_subject  (subject(100)),
  KEY idx_title    (title(120))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS eon_kb_questions (
  id            VARCHAR(32)  PRIMARY KEY,
  q             VARCHAR(400) NOT NULL,
  lang          VARCHAR(4)   NOT NULL,
  expect_lang   VARCHAR(4)   NOT NULL,
  vertical      VARCHAR(32)  NULL,
  module        VARCHAR(40)  NULL,
  intent        VARCHAR(32)  NOT NULL,
  facet         VARCHAR(32)  NOT NULL,
  answer_facet  VARCHAR(32)  NOT NULL,
  subject       VARCHAR(190) NULL,
  KEY idx_scope   (vertical, module, lang),
  KEY idx_intent  (intent, lang),
  KEY idx_answer  (answer_facet, vertical, module),
  KEY idx_q       (q(120)),
  FULLTEXT KEY ft_q (q)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- NO FULLTEXT INDEX ON eon_kb, and that is deliberate.
--
-- It was the obvious design and it is measurably wrong at this size. On
-- MariaDB 10.4 against this corpus, MATCH … AGAINST with ORDER BY relevance:
--     1 term    0.69s
--     3 terms   3.00s
--    12 terms  15.23s
-- because relevance ordering scores every matching row, and a word like
-- "invoice" matches tens of thousands of them. Retrieval routes through
-- eon_kb_questions instead (a 400-character column, a different proposition
-- entirely: 10-25ms) and falls back to a title LIKE inside an already-narrow
-- slice. Dropping ft_body took eon_kb from 284 MB to 198 MB — measured after
-- OPTIMIZE TABLE, which InnoDB needs to actually give the space back; before
-- it the table read 325 MB and looked like the change had made things worse.
--
-- Bangla and the fulltext index on the QUESTION bank: MySQL's default parser splits on whitespace
-- and punctuation, which is right for বাংলা (unlike Chinese or Japanese, where
-- the ngram parser is needed). But the default minimum word length is 4 for
-- InnoDB, and plenty of Bangla and English ERP words are shorter — "কত", "VAT",
-- "GL", "PAR". If those must be searchable, set
--   innodb_ft_min_token_size = 2
-- in my.cnf and rebuild the indexes. On shared hosting that setting is usually
-- not yours to change, which is why the retrieval layer never relies on
-- fulltext alone: it filters on the indexed scope columns first and uses
-- MATCH only to rank inside that slice.
