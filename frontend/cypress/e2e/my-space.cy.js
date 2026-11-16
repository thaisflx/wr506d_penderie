// « Mon espace » : chiffres de mon dressing, langue du compte et export de mes données (docs/decisions.md).
// Fixtures test : Camille a 14 objets, 2 looks publiés (T-shirt blanc + Ponceuse, Jean brut) et un brouillon.
describe('Mon espace', () => {
  beforeEach(() => {
    cy.loginAs('camille@test.local', '/profile')
    cy.getCy('profile-my-space').click()
    cy.location('pathname').should('eq', '/espace')
  })

  it('montre les chiffres de mon dressing', () => {
    cy.getCy('space-items').should('contain.text', '14')
    cy.getCy('space-looks').should('contain.text', '2')
    cy.getCy('space-followers').should('contain.text', '3')
    cy.getCy('space-following').should('contain.text', '1')
    cy.getCy('space-places').should('contain.text', 'Maison').and('contain.text', '13')
    cy.getCy('space-categories').find('li').first().should('contain.text', 'Hauts').and('contain.text', '3')
    cy.getCy('space-most-worn').find('li').should('have.length', 3)
    cy.getCy('space-never-worn').should('contain.text', 'Baskets montantes').and('not.contain.text', 'Ponceuse')
  })

  it('enregistre la langue sur mon compte et traduit l\'écran', () => {
    cy.intercept('PATCH', '/api/me').as('saveLocale')

    cy.getCy('space-locale').select('Deutsch')

    cy.wait('@saveLocale').its('request.body').should('deep.equal', { locale: 'de' })
    cy.getCy('space-locale-saved').should('be.visible')
    // L'écran passe en allemand, catégories proposées comprises (labelKey)
    cy.get('h1').should('have.text', 'Mein Bereich')
    cy.getCy('space-categories').find('li').first().should('contain.text', 'Oberteile')
    cy.reload()
    cy.getCy('space-locale').should('have.value', 'de')
    cy.getCy('space-export').should('have.text', 'Meine Daten herunterladen')
  })

  it('télécharge toutes mes données dans un fichier JSON', () => {
    cy.intercept('GET', '/api/me/export').as('export')

    cy.getCy('space-export').click()

    cy.wait('@export').then(({ response }) => {
      expect(response.statusCode).to.eq(200)
      const name = /filename="?([^";]+)/.exec(response.headers['content-disposition'])[1]
      cy.readFile(`cypress/downloads/${name}`).then((data) => {
        expect(data.profile.email).to.eq('camille@test.local')
        expect(data.looks).to.have.length(3)
      })
    })
  })
})
