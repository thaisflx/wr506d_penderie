describe('Navigation logement > pièce > rangement', () => {
  beforeEach(() => {
    cy.loginAs('camille@test.local')
  })

  it('affiche les pièces de la Maison', () => {
    cy.getCy('place-name').should('have.text', 'Maison')
    cy.getCy('room-tile')
      .find('.room-tile-name')
      .then((names) => [...names].map((name) => name.textContent))
      .should('have.members', ['Dressing', 'Grenier', 'Garage', 'Bureau', 'Collection'])
  })

  it('ouvre le Dressing puis la Penderie et ses objets', () => {
    cy.getCy('room-tile').contains('Dressing').click()

    cy.location('pathname').should('match', /^\/rooms\/\d+$/)
    cy.getCy('room-name').should('have.text', 'Dressing')
    cy.getCy('room-place').should('contain.text', 'Maison')
    cy.getCy('storage-name').then((names) => [...names].map((name) => name.textContent))
      .should('deep.equal', ['Penderie', 'Coffre à bijoux'])

    // Le premier rangement est déplié : ses catégories et leurs objets
    cy.getCy('storage').first().within(() => {
      cy.getCy('category-name').then((names) => [...names].map((name) => name.textContent))
        .should('deep.equal', ['Hauts', 'Chaussures'])
      cy.contains('[data-cy="category"]', 'Hauts').find('[data-cy="item-name"]')
        .then((names) => [...names].map((name) => name.textContent))
        .should('have.members', ['T-shirt blanc', 'Jean brut', 'Carnet intime'])
      cy.contains('[data-cy="category"]', 'Chaussures').find('[data-cy="item-name"]')
        .should('have.text', 'Baskets montantes')
    })
  })

  it('déplie un autre rangement de la pièce', () => {
    cy.getCy('room-tile').contains('Dressing').click()

    cy.contains('[data-cy="storage"]', 'Coffre à bijoux').within(() => {
      cy.getCy('storage-toggle').should('have.attr', 'aria-expanded', 'false').click()
      cy.getCy('item-name').should('have.text', 'Bague de grand-mère')
    })
  })

  it('revient à l\'accueil et ouvre une autre pièce', () => {
    cy.getCy('room-tile').contains('Dressing').click()
    cy.get('a[aria-label="Retour à l\'accueil"]').click()

    cy.getCy('room-tile').contains('Grenier').click()
    cy.getCy('room-name').should('have.text', 'Grenier')
    cy.getCy('storage').first().find('[data-cy="item-name"]')
      .then((names) => [...names].map((name) => name.textContent))
      .should('have.members', ['Abbey Road', 'Led Zeppelin IV', 'Thriller'])
  })
})
