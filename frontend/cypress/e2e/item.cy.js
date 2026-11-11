// Fiche objet. La base de test est rechargée avant chaque test (cypress/support/e2e.js) :
// le statut modifié ici revient tout seul à son état des fixtures.
const statusButton = (label) => cy.contains('[aria-label="État de l\'objet"] button', label)

describe('Fiche objet', () => {
  beforeEach(() => {
    cy.loginAs('camille@test.local')
    cy.getCy('room-tile').contains('Dressing').click()
    cy.contains('[data-cy="item-row"]', 'Jean brut').click()
  })

  it('change le statut depuis les pilules d\'état et le garde après rechargement', () => {
    cy.intercept('PATCH', '/api/items/*').as('patch')
    statusButton('Au sale').should('have.attr', 'aria-pressed', 'true')

    statusButton('Disponible').click()

    // Même PATCH que le formulaire « Modifier » : seul le statut est envoyé
    cy.wait('@patch').then(({ request, response }) => {
      expect(request.body).to.deep.equal({ status: 'available' })
      expect(response.statusCode).to.eq(200)
    })
    statusButton('Disponible').should('have.attr', 'aria-pressed', 'true')
    statusButton('Au sale').should('have.attr', 'aria-pressed', 'false')

    cy.reload()
    statusButton('Disponible').should('have.attr', 'aria-pressed', 'true')
  })
})
