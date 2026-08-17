/* EON 2 abilities — plug-in registry. Each plug-in is self-contained and registers itself:
   • an Ask EON domain      window.EonDomains.register({ id, priority, answer })
   • decisions              EonErpDecisions.addProvider((D, {company}) => [...])
   • a screen panel         window.EonApp?.registerPanel(section, { id, title, render })
   Add a line here for every plug-in. */
