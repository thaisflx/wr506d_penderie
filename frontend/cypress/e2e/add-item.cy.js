describe('Ajout d\'un objet', () => {
  beforeEach(() => {
    cy.loginAs('camille@test.local')
    cy.getCy('room-tile').contains('Dressing').click()
    cy.getCy('room-name').should('have.text', 'Dressing')
  })

  it('ajoute un pull dans la Penderie et le garde après rechargement', () => {
    cy.intercept('POST', '/api/items').as('createItem')

    cy.getCy('add-item').click()
    cy.getCy('sheet').should('have.attr', 'aria-label', 'Nouvel objet').within(() => {
      cy.getCy('field-name').type('Pull rayé')
      cy.getCy('field-category').select('Penderie › Hauts')
      cy.getCy('field-status').select('Au sale')
      cy.getCy('form-submit').click()
    })

    cy.wait('@createItem').its('response.statusCode').should('eq', 201)
    cy.getCy('sheet').should('not.exist')
    cy.contains('[data-cy="category"]', 'Hauts').within(() => {
      cy.contains('[data-cy="item-row"]', 'Pull rayé').should('contain.text', 'Au sale')
    })

    // Enregistré en base, pas seulement affiché
    cy.reload()
    cy.contains('[data-cy="category"]', 'Hauts').should('contain.text', 'Pull rayé')
  })

  it('propose toutes les catégories de la pièce', () => {
    cy.getCy('add-item').click()

    cy.getCy('field-category').find('option')
      .then((options) => [...options].map((option) => option.textContent.trim()))
      .should('deep.equal', ['Penderie › Hauts', 'Penderie › Chaussures', 'Coffre à bijoux › Bijoux'])
  })

  it('ferme le formulaire sans rien ajouter', () => {
    cy.getCy('add-item').click()
    cy.getCy('field-name').type('Objet abandonné')
    cy.get('[data-cy="sheet"] button[aria-label="Fermer"]').click()

    cy.getCy('sheet').should('not.exist')
    cy.contains('Objet abandonné').should('not.exist')
  })
})
