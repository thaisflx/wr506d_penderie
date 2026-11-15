// Profil : onglets « Mes looks » et « Brouillons · n » ; carte « look du jour » de Mon dressing (docs/decisions.md, lot 2).
// Fixtures test (TestOotdFixtures) : Camille a deux looks publiés (hier, il y a 3 jours) et un brouillon.
describe('Mes looks dans mon profil', () => {
  beforeEach(() => {
    cy.loginAs('camille@test.local', '/profile')
  })

  it('montre mes looks publiés, du plus récent au plus ancien', () => {
    cy.getCy('profile-tab-looks').should('have.attr', 'aria-selected', 'true')
    cy.getCy('profile-tab-drafts').should('have.text', 'Brouillons · 1')

    cy.getCy('profile-looks').find('[data-cy="ootd-caption"]').then((els) => [...els].map((el) => el.textContent))
      .should('deep.equal', ['Tenue de bricolage du samedi', 'Jean brut et rien d\'autre'])
    cy.getCy('ootd-author').should('not.exist')
    cy.getCy('draft-card').should('not.exist')
  })

  it('ouvre la fiche d\'un objet porté dans mon look', () => {
    cy.getCy('ootd-card').first().contains('[data-cy="worn-item"]', 'Ponceuse').click()

    cy.location('pathname').should('match', /^\/items\/\d+$/)
  })

  it('reprend un brouillon depuis l\'onglet Brouillons', () => {
    cy.getCy('profile-tab-drafts').click()

    cy.getCy('ootd-card').should('not.exist')
    cy.getCy('draft-card').should('have.length', 1).and('contain.text', 'Idée pour la soirée').and('contain.text', '1 objet')
    cy.getCy('draft-card').click()

    cy.location('pathname').should('match', /^\/looks\/\d+\/modifier$/)
    cy.getCy('post-look-caption').should('have.value', 'Idée pour la soirée')
  })

  it('ne compte plus un brouillon supprimé', () => {
    cy.getCy('profile-tab-drafts').click()
    cy.getCy('draft-card').click()
    cy.getCy('post-look-delete').click()

    cy.location('pathname').should('eq', '/profile')
    cy.getCy('profile-tab-drafts').should('have.text', 'Brouillons · 0').click()
    cy.getCy('profile-drafts-empty').should('be.visible')
  })
})

describe('Profil sans look', () => {
  it('propose de poster un look quand je n\'en ai publié aucun', () => {
    cy.intercept('GET', '/api/me/ootds', { body: { member: [] } })
    cy.loginAs('camille@test.local', '/profile')

    cy.getCy('profile-tab-drafts').should('have.text', 'Brouillons · 0')
    cy.getCy('profile-looks-empty').contains('a', 'Poster mon look').click()
    cy.location('pathname').should('eq', '/looks/nouveau')
  })
})

describe('Look du jour sur Mon dressing', () => {
  it('ouvre l\'écran pour poster mon look', () => {
    cy.loginAs('camille@test.local', '/dressing')

    cy.getCy('home-post-look').should('not.have.attr', 'aria-disabled')
    cy.getCy('home-post-look').click()

    cy.location('pathname').should('eq', '/looks/nouveau')
    cy.getCy('post-look').should('be.visible')
  })
})
