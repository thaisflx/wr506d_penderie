// Supprimer un look publié : sur mes looks seulement (fil, profil), avec confirmation (docs/decisions.md, lot 2).
// Fixtures test : Camille a publié « Tenue de bricolage du samedi » et « Jean brut et rien d'autre » ;
// Sophie, son amie proche, a publié « Prête pour le marché ». Les photos sont dans uploads/demo : jamais effacées.
const MY_LOOK = 'Tenue de bricolage du samedi'
const card = (caption) => cy.contains('[data-cy="ootd-card"]', caption)

describe('Supprimer mon look', () => {
  beforeEach(() => {
    cy.loginAs('camille@test.local')
  })

  it('le supprime depuis le fil, sans recharger la page, et il disparaît aussi du profil', () => {
    cy.window().then((win) => (win.notReloaded = true))
    card(MY_LOOK).find('[data-cy="ootd-delete"]').click()
    cy.getCy('sheet').should('contain.text', 'Supprimer ce look ?').and('contain.text', 'Sa photo sera effacée.')

    cy.getCy('ootd-delete-confirm').click()

    cy.getCy('sheet').should('not.exist')
    card(MY_LOOK).should('not.exist')
    card('Jean brut et rien d\'autre').should('exist')
    cy.window().its('notReloaded').should('eq', true)

    cy.getCy('nav-profile').click()
    cy.getCy('profile-looks').should('not.contain.text', MY_LOOK).and('contain.text', 'Jean brut et rien d\'autre')
  })

  it('le supprime depuis l\'onglet Mes looks du profil', () => {
    cy.getCy('nav-profile').click()
    card(MY_LOOK).find('[data-cy="ootd-delete"]').click()
    cy.getCy('ootd-delete-confirm').click()

    card(MY_LOOK).should('not.exist')
    cy.getCy('ootd-card').should('have.length', 1)
  })

  it('garde le look si je renonce', () => {
    card(MY_LOOK).find('[data-cy="ootd-delete"]').click()
    cy.getCy('sheet').contains('button', 'Garder').click()

    cy.getCy('sheet').should('not.exist')
    card(MY_LOOK).should('exist')
  })

  it('ne propose pas de supprimer le look de quelqu\'un d\'autre', () => {
    card('Prête pour le marché').should('be.visible').find('[data-cy="ootd-delete"]').should('not.exist')
    card(MY_LOOK).find('[data-cy="ootd-delete"]').should('exist')
  })

  it('ne propose pas de supprimer sur le profil public de Sophie', () => {
    card('Prête pour le marché').find('[data-cy="ootd-author"]').click()

    cy.getCy('public-looks').find('[data-cy="ootd-card"]').should('have.length', 1)
    cy.getCy('ootd-delete').should('not.exist')
  })
})
