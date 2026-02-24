/**
 * Configurazione Google Tag Manager
 * 
 * Questo file gestisce la configurazione centralizzata del GTM.
 * Per disabilitare GTM, imposta enabled: false
 * Per cambiare il container ID, modifica containerId
 */

window.GTM_CONFIG = {
	// Abilita/disabilita Google Tag Manager
	enabled: true,
	
	// ID del container GTM
	containerId: 'GTM-PTJCW7QX',
	
	// Configurazioni aggiuntive
	debug: false, // Abilita log di debug
	
	// Funzione per abilitare/disabilitare GTM dinamicamente
	toggle: function(enabled) {
		this.enabled = enabled;
		console.log(`GTM ${enabled ? 'abilitato' : 'disabilitato'}`);
	},
	
	// Funzione per cambiare container ID
	setContainerId: function(id) {
		this.containerId = id;
		console.log(`GTM Container ID cambiato in: ${id}`);
	}
};

// Log di inizializzazione (solo in debug)
if (window.GTM_CONFIG.debug) {
	console.log('🔧 GTM Config caricato:', window.GTM_CONFIG);
} 