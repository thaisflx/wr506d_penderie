import './commands'

// Le navigateur de Cypress est en anglais : sans choix déjà fait, l'application démarrerait en anglais. Les tests sont
// écrits en français, la langue par défaut ; i18n.cy.js change de langue lui-même (ce choix est alors gardé)
Cypress.on('window:before:load', (win) => {
  if (!win.localStorage.getItem('penderie_locale')) win.localStorage.setItem('penderie_locale', 'fr')
})

// Exécuté avant chaque test, de chaque spec : les tests sont indépendants les uns des autres.
beforeEach(() => {
  // Base de test remise à l'état des fixtures du groupe test (docs/campagne-test.md).
  // La base de dev (penderie_db) n'est jamais touchée.
  cy.exec('make -C .. fixtures-test', { timeout: 60000 })

  // Toutes les requêtes de l'application vers l'API et les photos portent l'en-tête qui fait
  // passer Symfony en environnement test, donc sur penderie_db_test (voir docker/nginx/default.conf).
  cy.intercept({ url: /\/(api|uploads)\// }, (req) => {
    req.headers['x-penderie-env'] = 'test'
  })
})
