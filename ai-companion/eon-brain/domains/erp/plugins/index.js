/* EON 2 abilities — plug-in registry. Each plug-in is self-contained and registers itself:
   • an Ask EON domain      window.EonDomains.register({ id, priority, answer })
   • decisions              EonErpDecisions.addProvider((D, {company}) => [...])
   • a screen panel         window.EonApp?.registerPanel(section, { id, title, render })
   Add a line here for every plug-in. */
import './bangla.js';        // বাংলা — Bangla brief and Bangla answers
import './health.js';        // company health score — one number per company, with drivers
import './since.js';         // since yesterday — what moved since the last snapshot
import './compliance.js';    // Bangladesh statutory calendar — VAT, TDS, licences, RJSC
import './prefs.js';         // what EON remembers about the boss
import './boardpack.js';     // printable board pack
import './delegate.js';      // delegation & follow-through — EON chases what the boss handed out
import './whatif.js';        // what-if simulator — levers over the real ledger
import './navigator.js';     // knows the ERP itself: every screen, menu path, action and table
import './entity.js';        // one person, one party, one thing: tasks, dues, assignments, errors
import './act.js';           // EON does the work through the ERP's own forms (with a confirmation gate)
import './understand.js';    // verb + subject + who, in Bangla and English, and half commands are held
