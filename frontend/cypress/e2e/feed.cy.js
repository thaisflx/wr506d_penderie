// Fil des looks du jour (accueil) et looks d'un profil public (docs/decisions.md, lot 2).
// Fixtures test : Camille a deux looks publiés (hier : T-shirt blanc + Ponceuse ; il y a 3 jours : Jean brut)
// et un brouillon ; Sophie, son amie proche, a publié un look il y a 2 h.
const captions = () => cy.getCy('ootd-caption').then((els) => [...els].map((el) => el.textContent.trim()))

describe('Fil des looks du jour', () => {
  it('Camille voit le look de Sophie et les siens, du plus récent au plus ancien, sans son brouillon', () => {
    cy.loginAs('camille@test.local')

    captions().should('deep.equal', ['Prête pour le marché', 'Tenue de bricolage du samedi', 'Jean brut et rien d\'autre'])
    cy.getCy('ootd-author').first().should('have.text', 'sophie')
    // Intl met une espace insécable fine avant « h » (typographie française) : espaces ramenées à des espaces simples
    cy.getCy('ootd-when').first().invoke('text').should((text) => expect(text.replace(/\s/g, ' ')).to.eq('il y a 2 h'))
    // Camille est amie proche de Sophie ; pas de puce sur ses propres looks
    cy.getCy('ootd-card').first().find('[data-cy="ootd-relation"]').should('have.text', 'Ami proche')
    cy.getCy('ootd-card').eq(1).find('[data-cy="ootd-relation"]').should('not.exist')
    cy.getCy('ootd-card').eq(1).find('[data-cy="ootd-when"]').should('have.text', 'hier')
    cy.contains('Idée pour la soirée').should('not.exist')
  })

  it('mes objets ouvrent leur fiche', () => {
    cy.loginAs('camille@test.local')

    cy.contains('[data-cy="ootd-card"]', 'Tenue de bricolage du samedi').contains('[data-cy="worn-item"]', 'Ponceuse').click()

    cy.location('pathname').should('match', /^\/items\/\d+$/)
    cy.get('.item-title').should('have.text', 'Ponceuse')
  })

  it('Théo voit les objets de Camille en lecture seule, sans leur emplacement', () => {
    cy.loginAs('theo@test.local')

    captions().should('deep.equal', ['Tenue de bricolage du samedi', 'Jean brut et rien d\'autre'])
    cy.getCy('ootd-relation').first().should('have.text', 'Abonné')
    // La Ponceuse vient du Garage (amis proches) : Théo la voit dans le look, pas l'endroit où elle est rangée
    cy.contains('[data-cy="worn-item"]', 'T-shirt blanc').click()

    cy.location('pathname').should('eq', '/')
    cy.getCy('item-preview-category').should('have.text', 'Hauts')
    cy.getCy('item-preview-brand').should('have.text', 'Petit Bateau')
    cy.getCy('sheet').should('not.contain.text', 'Penderie').and('not.contain.text', 'Dressing').and('not.contain.text', 'Maison')
  })

  it('« Voir plus » ajoute la page suivante, puis disparaît', () => {
    // Les fixtures n'ont que 3 looks : deux pages simulées (la pagination de l'API est testée dans OotdApiTest)
    const look = (id, caption) => ({
      id, caption, photo: null, publishedAt: new Date().toISOString(), wornItems: [],
      owner: { id: 999, username: 'sophie', avatar: null },
    })
    cy.intercept('GET', '/api/ootds', { member: [look(1, 'Première page')], view: { next: '/api/ootds?page=2' } })
    cy.intercept('GET', '/api/ootds?page=2', { member: [look(2, 'Deuxième page')], view: {} }).as('page2')
    cy.loginAs('camille@test.local')

    captions().should('deep.equal', ['Première page'])
    cy.getCy('feed-more').click()
    cy.wait('@page2')

    captions().should('deep.equal', ['Première page', 'Deuxième page'])
    cy.getCy('feed-more').should('not.exist')
  })

  it('Zoé, qui ne suit personne, voit un fil vide avec des pistes', () => {
    cy.loginAs('zoe@test.local')

    cy.getCy('feed-empty').should('be.visible')
    cy.getCy('feed-find-friends').should('be.visible')
    cy.getCy('ootd-card').should('not.exist')
  })
})

describe('Looks d\'un profil public', () => {
  it('Théo voit les looks publiés de Camille sur son profil', () => {
    cy.loginAs('theo@test.local')
    cy.getCy('nav-friends').click()
    cy.getCy('friends-tab-search').click()
    cy.getCy('friends-search').type('cam')
    cy.contains('[data-cy="search-result"]', 'camille').click()

    cy.getCy('public-looks').within(() => {
      captions().should('deep.equal', ['Tenue de bricolage du samedi', 'Jean brut et rien d\'autre'])
      cy.getCy('ootd-author').should('not.exist')
    })
  })

  it('Zoé, inconnue, ne voit aucun look de Camille', () => {
    cy.loginAs('zoe@test.local')
    cy.getCy('nav-friends').click()
    cy.getCy('friends-tab-search').click()
    cy.getCy('friends-search').type('cam')
    cy.contains('[data-cy="search-result"]', 'camille').click()

    cy.getCy('public-looks-empty').should('be.visible')
  })
})
