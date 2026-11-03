/* ============================================================
   EON · import-owner-bank — the owner's question files (Downloads, .sql) into
   JSON the runners read. Handles both formats he has sent:

     EON_Chatbot_Question_Variants.sql            → tools/owner-questions.json
     EON_Accounts_Finance_Question_Variants.sql   → tools/owner-accounts-questions.json
                                                  + tools/owner-chains.json (multi-turn conversations)

     node tools/import-owner-bank.mjs <file.sql>
   ============================================================ */
import { readFileSync, writeFileSync } from 'node:fs';
const file = process.argv[2];
const s = readFileSync(file, 'utf8');
const V = "'((?:[^']|'')*)'";
const un = (x) => x.replace(/''/g, "'");
const count = (rows, k) => Object.entries(rows.reduce((a, r) => (a[r[k]] = (a[r[k]] || 0) + 1, a), {})).sort((a, b) => b[1] - a[1]);

if (/chatbot_accounts_questions/.test(s)) {
  const rx = new RegExp("\\('(A\\d+)'," + Array(12).fill(V).join(',') + '\\)', 'g');
  const rows = []; let m;
  while ((m = rx.exec(s))) rows.push({ id: m[1], business: 'অ্যাকাউন্টস', module: un(m[2]), section: un(m[3]), intent: un(m[4]), type: un(m[5]), q: un(m[6]), lang: m[7], context: m[8], slots: un(m[9]), action: un(m[10]), response: un(m[11]), accounting: un(m[12]), difficulty: m[13] });
  const chains = []; const cx = new RegExp("\\('(C\\d+)'," + V + ',(\\d+),' + V + ',' + V + ',' + V + '\\)', 'g');
  while ((m = cx.exec(s))) chains.push({ chain: m[1], pattern: un(m[2]), turn: +m[3], speaker: m[4], msg: un(m[5]), note: un(m[6]) });
  writeFileSync(new URL('owner-accounts-questions.json', import.meta.url), JSON.stringify(rows));
  writeFileSync(new URL('owner-chains.json', import.meta.url), JSON.stringify(chains));
  console.log(rows.length, 'questions,', new Set(chains.map((x) => x.chain)).size, 'conversations,', chains.length, 'turns');
  console.log('MODULE', count(rows, 'module'));
  console.log('TYPE', count(rows, 'type'));
  console.log('LANG', count(rows, 'lang'));
  console.log('INTENT', count(rows, 'intent').slice(0, 30));
} else {
  const rx = new RegExp("\\('(Q\\d+)'," + Array(11).fill(V).join(',') + '\\)', 'g');
  const rows = []; let m;
  while ((m = rx.exec(s))) rows.push({ id: m[1], business: un(m[2]), module: un(m[3]), intent: un(m[4]), type: m[5], q: un(m[6]), lang: m[7], slots: un(m[8]), action: un(m[9]), response: un(m[10]), accounting: un(m[11]), difficulty: m[12] });
  writeFileSync(new URL('owner-questions.json', import.meta.url), JSON.stringify(rows));
  console.log(rows.length, 'questions'); console.log('TYPE', count(rows, 'type'));
}
