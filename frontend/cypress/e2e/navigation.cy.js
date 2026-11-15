describe('Barre de navigation', () => {
  beforeEach(() => {
    cy.loginAs('camille@test.local')
  })

  it('arrive sur le fil des looks du jour, puis ouvre Mon dressing et Tous mes objets', () => {
    cy.location('pathname').should('eq', '/')
    cy.getCy('feed-title').should('be.visible')
    cy.getCy('nav-home').should('have.attr', 'aria-current', 'page')
    cy.getCy('home-bell').should('be.visible')

    cy.getCy('nav-dressing').click()
    cy.location('pathname').should('eq', '/dressing')
    cy.getCy('nav-dressing').should('have.attr', 'aria-current', 'page')
    cy.getCy('home-username').should('have.text', 'camille')
    // Une seule cloche, sur le fil
    cy.getCy('home-bell').should('not.exist')

    cy.getCy('dressing-all-items').click()
    cy.location('pathname').should('eq', '/dressing/objets')
    cy.getCy('nav-dressing').should('have.attr', 'aria-current', 'page')
    cy.getCy('items-back').click()
    cy.location('pathname').should('eq', '/dressing')
  })

  it('cherche un objet depuis Mon dressing', () => {
    cy.getCy('nav-dressing').click()
    cy.get('#home-search').type('jean{enter}')

    cy.location('pathname').should('eq', '/dressing/objets')
    cy.location('search').should('eq', '?q=jean')
  })

  it('ouvre et ferme l\'écran pour poster un look', () => {
    cy.getCy('feed-post').click()
    cy.location('pathname').should('eq', '/looks/nouveau')
    cy.get('.bottom-nav').should('not.exist')

    cy.getCy('post-look-close').click()
    cy.location('pathname').should('eq', '/')
  })

  it('« Trouver des amis » ouvre la recherche de la page Amis', () => {
    // Zoé ne suit personne : son fil est vide et propose de trouver des amis
    cy.loginAs('zoe@test.local')
    cy.getCy('feed-find-friends').click()

    cy.location('pathname').should('eq', '/friends')
    cy.getCy('friends-tab-search').should('have.attr', 'aria-selected', 'true')
    cy.getCy('friends-search').should('be.visible')
  })
})

describe('Navigation logement > pièce > rangement', () => {
  beforeEach(() => {
    cy.loginAs('camille@test.local', '/dressing')
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

  it('revient à Mon dressing et ouvre une autre pièce', () => {
    cy.getCy('room-tile').contains('Dressing').click()
    cy.get('a[aria-label="Retour à Mon dressing"]').click()
    cy.location('pathname').should('eq', '/dressing')

    cy.getCy('room-tile').contains('Grenier').click()
    cy.getCy('room-name').should('have.text', 'Grenier')
    cy.getCy('storage').first().find('[data-cy="item-name"]')
      .then((names) => [...names].map((name) => name.textContent))
      .should('have.members', ['Abbey Road', 'Led Zeppelin IV', 'Thriller'])
  })
})
