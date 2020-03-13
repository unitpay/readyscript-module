





            <tr>
                <td class="otitle">{$elem.__domain->getTitle()}&nbsp;&nbsp;{if $elem.__domain->getHint() != ''}<a class="help-icon" title="{$elem.__domain->getHint()|escape}">?</a>{/if}
                </td>
                <td>{include file=$elem.__domain->getRenderTemplate() field=$elem.__domain}</td>
            </tr>

            <tr>
                <td class="otitle">{$elem.__public_key->getTitle()}&nbsp;&nbsp;{if $elem.__public_key->getHint() != ''}<a class="help-icon" title="{$elem.__public_key->getHint()|escape}">?</a>{/if}
                </td>
                <td>{include file=$elem.__public_key->getRenderTemplate() field=$elem.__public_key}</td>
            </tr>
                                            
            <tr>
                <td class="otitle">{$elem.__secret_key->getTitle()}&nbsp;&nbsp;{if $elem.__secret_key->getHint() != ''}<a class="help-icon" title="{$elem.__secret_key->getHint()|escape}">?</a>{/if}
                </td>
                <td>{include file=$elem.__secret_key->getRenderTemplate() field=$elem.__secret_key}</td>
            </tr>
                                            
            <tr>
                <td class="otitle">{$elem.__language->getTitle()}&nbsp;&nbsp;{if $elem.__language->getHint() != ''}<a class="help-icon" title="{$elem.__language->getHint()|escape}">?</a>{/if}
                </td>
                <td>{include file=$elem.__language->getRenderTemplate() field=$elem.__language}</td>
            </tr>
                                            
            <tr>
                <td class="otitle">{$elem.____help__->getTitle()}&nbsp;&nbsp;{if $elem.____help__->getHint() != ''}<a class="help-icon" title="{$elem.____help__->getHint()|escape}">?</a>{/if}
                </td>
                <td>{include file=$elem.____help__->getRenderTemplate() field=$elem.____help__}</td>
            </tr>
                        