describe('Déconnexion', () => {
  beforeEach(() => {
    cy.loginAs('camille@test.local')
    cy.getCy('home-username').should('have.text', 'camille')
  })

  it('déconnecte depuis les paramètres du profil', () => {
    cy.getCy('nav-profile').click()
    cy.getCy('profile-settings').click()
    cy.getCy('logout').click()

    cy.location('pathname').should('eq', '/login')
    cy.window().its('localStorage.penderie_token').should('not.exist')
  })

  it('ne laisse plus accéder aux pages protégées', () => {
    cy.getCy('nav-profile').click()
    cy.getCy('profile-settings').click()
    cy.getCy('logout').click()
    cy.location('pathname').should('eq', '/login')

    cy.visit('/')
    cy.location('pathname').should('eq', '/login')
    cy.getCy('login-form').should('be.visible')
  })
})
