<?php

/*  
 *  FileTrader - Web based file sharing platform
 *  Copyright (C) 2011 François Kooman <fkooman@tuxed.net>
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU Affero General Public License as published by
 *  the Free Software Foundation, either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU Affero General Public License for more details.
 *
 *  You should have received a copy of the GNU Affero General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

require_once (getConfig($config, 'ssp_path', TRUE) . '/lib/_autoload.php');

class SSPAuth extends Auth {
	var $ssp;

	function __construct($config) {
		parent :: __construct($config);
		$this->ssp = new SimpleSAML_Auth_Simple(getConfig($this->config, 'ssp_sp', TRUE));
	}

	function login() {
		if ($this->isLoggedIn())
			return;

        $this->ssp->requireAuth(array("saml:NameIDPolicy" => "urn:oasis:names:tc:SAML:2.0:nameid-format:persistent"));
        $nameId = $this->ssp->getAuthData("saml:sp:NameID");
        if ("urn:oasis:names:tc:SAML:2.0:nameid-format:persistent" !== $nameId['Format']) {
            throw new Exception("NameID format MUST be 'urn:oasis:names:tc:SAML:2.0:nameid-format:persistent', we got '" . $nameId['Format'] . "' instead.");
        }
		$attr = $this->ssp->getAttributes();
		
		$_SESSION['userId'] = $nameId->value;
        $_SESSION['userAttr'] = $attr;
		$_SESSION['userDisplayName'] = $attr[getConfig($this->config, 'ssp_dn_attr', TRUE)][0];
        $_SESSION['userSchacHomeOrganization'] = $attr[getConfig($this->config, 'ssp_org_attr', TRUE)][0];
	}

	function logout($url = NULL) {
		parent :: logout(NULL);
		$this->ssp->logout($url);
	}
}
?>
