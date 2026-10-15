// Mot de passe de tous les comptes des fixtures test
export const PASSWORD = 'password'

// Même clé que src/api/client.js
const TOKEN_KEY = 'penderie_token'

/**
 * Connexion rapide par l'API (la connexion par le formulaire est testée dans login.cy.js),
 * puis ouverture de la page avec le jeton déjà enregistré, comme après une vraie connexion.
 */
Cypress.Commands.add('loginAs', (email, path = '/') => {
  cy.request({
    method: 'POST',
    url: '/api/login_check',
    // cy.request ne passe pas par cy.intercept : l'en-tête est ajouté ici
    headers: { 'X-Penderie-Env': 'test' },
    body: { email, password: PASSWORD },
  }).then(({ body }) => {
    cy.visit(path, {
      onBeforeLoad(win) {
        win.localStorage.setItem(TOKEN_KEY, body.token)
      },
    })
  })
})

Cypress.Commands.add('getCy', (name, options) => cy.get(`[data-cy="${name}"]`, options))
