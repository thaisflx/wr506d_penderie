import { PASSWORD } from '../support/commands'

describe('Connexion', () => {
  beforeEach(() => {
    cy.visit('/login')
  })

  function submit(email, password) {
    cy.getCy('login-email').type(email)
    cy.getCy('login-password').type(password, { log: false })
    cy.getCy('login-submit').click()
  }

  it('connecte Camille et ouvre son accueil', () => {
    submit('camille@test.local', PASSWORD)

    cy.location('pathname').should('eq', '/')
    cy.getCy('home-username').should('have.text', 'camille')
    cy.getCy('place-name').should('have.text', 'Maison')
    cy.window().its('localStorage.penderie_token').should('be.a', 'string')
  })

  it('refuse un mauvais mot de passe', () => {
    submit('camille@test.local', 'mauvais-mot-de-passe')

    cy.getCy('login-error').should('have.text', 'Email ou mot de passe incorrect.')
    cy.location('pathname').should('eq', '/login')
    cy.window().its('localStorage.penderie_token').should('not.exist')
  })

  it('refuse un compte suspendu avec un message clair', () => {
    submit('suspendu@test.local', PASSWORD)

    cy.getCy('login-error').should('have.text', 'Ce compte est suspendu.')
    cy.location('pathname').should('eq', '/login')
    cy.window().its('localStorage.penderie_token').should('not.exist')
  })

  it('renvoie vers la connexion puis vers la page demandée', () => {
    cy.visit('/profile')
    cy.location('pathname').should('eq', '/login')

    submit('camille@test.local', PASSWORD)

    cy.location('pathname').should('eq', '/profile')
  })
})
