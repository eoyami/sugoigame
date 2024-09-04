<?php
function menu_link($ses, $text, $img, $title, $href_prefix = "./?ses=", $class = "link_content", $id = "", $data = "")
{
    $sess = $_GET["sessao"];
    global $userDetails;
    global $sistemas_por_sessao;
    if (isset($sistemas_por_sessao[$ses]) && ! $userDetails->is_sistema_desbloqueado($sistemas_por_sessao[$ses])) {
        return "";
    }

    if ($class != 'link_content')
        $href_prefix = '';

    return "<li class=\"" . ($sess == $ses ? "active" : "") . "\">
			<a id=\"$id\" href=\"$href_prefix$ses\" class=\"$class \" title=\"$title\" $data>
				 <i class=\"$img fa-fw\"></i> <p> $text</p>" . ($userDetails->has_alert($ses) ? get_alert("pull-right") : "") . "
			</a>
		</li>";
}

function super_menu_link($href, $href_toggle, $text, $super_menu, $icon, $sistemas = [])
{
    global $userDetails;

    $ativo = count($sistemas) ? false : true;

    foreach ($sistemas as $sistema) {
        if ($userDetails->is_sistema_desbloqueado($sistema)) {
            $ativo = true;
            break;
        }
    }

    return $ativo ? "<div class=\"nav navbar-nav text-left\">
				<a href=\"#$href_toggle\" class=\"" . super_menu_active($super_menu) . "\" data-toggle=\"collapse\" data-parent=\"#vertical-menu\">
					<img src=\"Imagens/Icones/Sessoes/$icon.png\"/>
					<span class='super-menu-text'>$text</span>
					" . ($userDetails->has_super_alert($super_menu) ? get_alert("pull-right") : "") . "
				</a>
			</div>" : "";
}

function super_menu_in_out($menu)
{
    return "submenu panel w-100";
}

function super_menu_active($menu)
{
    return super_menu_can_be_active($menu) ? "active" : "";
}

function super_menu_can_be_active($menu)
{
    return get_super_menu() == $menu;
}
?>

<?php include "Includes/Components/Header/missoes_auxiliares.php"; ?>
<?php include "Includes/Components/Header/torneio_poneglyph.php"; ?>

<div id="vertical-menu">
    <div class="panel border-none">
        <?= super_menu_link("home", "menu-principal", "Principal", "principal", "principal") ?>
        <?php if ($userDetails->tripulacao && ($userDetails->in_ilha || $userDetails->tripulacao_alive)) : ?>
            <div id="menu-principal" class="collapse <?= super_menu_in_out("principal") ?>">
                <ul class="menu-vertical">
                    <?= menu_link("home", "Home", "", "Mantenha-se informado! Nunca se sabe a hora em que algo importante poderá acontecer.") ?>
                    <?= menu_link("recrutamento", "Recrute um Amigo", "", "") ?>
                    <?= menu_link("akumaBook", "Akuma Book", "", "Veja quais foram as Akuma no Mi já encontradas","", "akumas-book","","") ?>
                    <?= menu_link("hall", "Hall da fama", "", "Veja quais foram os melhores jogadores de eras passadas") ?>
                    <?= menu_link("ranking", "Ranking", "", "") ?>
                    <?= menu_link("conta", "Minha Conta", "", "") ?>
                     <?= menu_link("calculadoras", "Calculadoras", "", "") ?>
                    <?= menu_link("#", "Destravar Tripulação", "", "Corrigir bugs que podem ter travado sua conta.", "", "", "unstuck-acc") ?>
                    <?= menu_link("vipLoja", "Gold Shop", "", "") ?>
                    <?= menu_link("vipComprar", "Faça uma doação", "", "") ?>
                    <?= menu_link("#", "Selecionar tripulação", "", "É hora de dar tchau!", "", "link_redirect", "link_Scripts/Geral/deslogartrip") ?>
                    <?= menu_link("#", "Logout", "", "É hora de dar tchau!", "", "link_redirect", "link_Scripts/Geral/deslogar") ?>
                    
                </ul>
            </div>
            <?php if (! $userDetails->combate_pvp && ! $userDetails->combate_pve && ! $userDetails->combate_bot) : ?>
                <?php if ($userDetails->tripulacao["campanha_impel_down"] || $userDetails->tripulacao["campanha_enies_lobby"]) : ?>
                    <?= super_menu_link("campanhaImpelDown", "menu-campanha", "Campanhas", "campanha", "campanha") ?>
                    <div id="menu-campanha" class="collapse <?= super_menu_in_out("campanha") ?>">
                        <ul class="menu-vertical">
                            <?php if ($userDetails->tripulacao["campanha_impel_down"]) : ?>
                                <?= menu_link("campanhaImpelDown", "Impel Down", "", "") ?>
                            <?php endif; ?>
                            <?php if ($userDetails->tripulacao["campanha_enies_lobby"]) : ?>
                                <?= menu_link("campanhaEniesLobby", "Enies Lobby", "", "") ?>
                            <?php endif; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <?= super_menu_link("status", "menu-tripulacao", "Tripulação", "tripulacao", "tripulacao", [
                    SISTEMA_VISAO_GERAL_TRIPULACAO,
                    SISTEMA_HAKI
                ]) ?>

                <div id="menu-tripulacao" class="collapse <?= super_menu_in_out("tripulacao") ?>">
                    <ul class="menu-vertical">
                        <?= menu_link("tripulacao", "Visão geral", "", "") ?>
                        <?= menu_link("status", "Tripulantes", "", "") ?>
                        <?= menu_link("karma", "Karma", "", "") ?>
                        <?= menu_link("realizacoes", "Conquistas", "", "") ?>
                        <?= menu_link("listaNegra", "Lista Negra", "", "") ?>
                        <?= menu_link("tatics", "Táticas", "", "") ?>
                        <?= menu_link("combateLog", "Histórico de Combates", "", "") ?>
                        <?= menu_link("wantedLog", "Histórico de Recompensas", "", "") ?>
                    </ul>
                </div>
                <?php if ($userDetails->navio) : ?>
                    <?= super_menu_link("statusNavio", "menu-navio", "Navio", "navio", "navio") ?>
                    <div id="menu-navio" class="collapse <?= super_menu_in_out("navio") ?>">
                        <ul class="menu-vertical">
                            <?= menu_link("statusNavio", "Visão Geral", "", "") ?>
                            <?= menu_link("navioSkin", "Aparência", "", "") ?>
                            <?= menu_link("obstaculos", "Obstáculos do Navio", "glyphicon glyphicon-knight", "") ?>
                            <?php if (! $userDetails->tripulacao["recrutando"] && ! $userDetails->missao) : ?>
                                <?= menu_link("quartos", "Enfermaria", "", "") ?>
                                <?= menu_link("forja", "Forja", "", "") ?>
                                <?= menu_link("oficina", "Oficina", "", "") ?>
                            <?php endif; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                <?php if ($userDetails->in_ilha) : ?>
                    <?= super_menu_link($userDetails->tripulacao["recrutando"]
                        ? "recrutar"
                        : ($userDetails->missao_r
                            ? "missoesR"
                            : "missoes"), "menu-ilha", "Ilha Atual", "ilha", "ilha") ?>

                    <div id="menu-ilha" class="collapse <?= super_menu_in_out("ilha") ?>">
                        <ul class="menu-vertical">
                            <?= menu_link("missoes", "Missões", "", "Aventure-se! Essa ilha tem muito a ser explorado!") ?>
                            <?= menu_link("incursao", "Incursão", "", "") ?>
                            <?= menu_link("recrutar", "Recrutar", "", "") ?>
                            <?php if (! $userDetails->tripulacao["recrutando"]) : ?>
                                <?php if (count($userDetails->personagens) > 1) : ?>
                                    <?= menu_link("expulsar", "Expulsar Trip.", "", "") ?>
                                <?php endif; ?>

                                <?= menu_link("tripulantesInativos", "Tripulantes fora do barco", "", "") ?>
                                <?= menu_link("politicaIlha", "Domínio da Ilha", "", "") ?>
                                <?= menu_link("mercado", "Mercado", "", "") ?>
                                <?= menu_link("restaurante", "Restaurante", "", "") ?>
                                <?= menu_link("upgrader", "Aprimoramentos", "", "") ?>
                                <?= menu_link("estaleiro", "Estaleiro", "", "") ?>
                                <?= menu_link("hospital", "Hospital", "", "") ?>
                                <?= menu_link("profissoesAprender", "Escola de Profissões", "", "") ?>
                                <?= menu_link("missoesCaca", "Missões de caça", "", "") ?>
                                <?= menu_link("missoesR", "Pesquisas", "", "Pesquise para evoluir continuamente.") ?>
                            <?php endif; ?>
                            <?php if ($userDetails->ilha["ilha"] == 47) : ?>
                                <?= menu_link("arvoreAnM", "Jardim de Laftel", "", "") ?>
                            <?php endif; ?>

                            <?php if (
                                ($userDetails->tripulacao["x"] != $userDetails->tripulacao["res_x"]
                                    || $userDetails->tripulacao["y"] != $userDetails->tripulacao["res_y"])
                                && $userDetails->in_ilha
                            ) : ?>
                                <?= menu_link("Geral/ilha_salvar_respown.php", "Salvar retorno", "", "Venha para esta ilha quando sua tripulação for derrotada.", "", "link_confirm", "", "data-question=\"Tem certeza que deseja salvar seu retorno nessa ilha?\"") ?>

                            <?php endif; ?>

                            <?php if ((($userDetails->ilha["ilha"] == 42 and $userDetails->tripulacao["faccao"] == FACCAO_PIRATA)
                                or ($userDetails->ilha["ilha"] == 43 and $userDetails->tripulacao["faccao"] == FACCAO_MARINHA))
                                and $userDetails->capitao["lvl"] >= 45
                            ) : ?>
                                <?= menu_link("Geral/novo_mundo.php", "Ir para o Novo Mundo", "", "Hora de navegar! Rumo ao desconhecido!", "", "link_sends") ?>
                            </ul>
                        <?php endif; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                <?php if ($userDetails->navio) : ?>
                    <?= super_menu_link("oceano", "menu-oceano", "Oceano", "oceano", "oceano", [SISTEMA_OCEANO]) ?>
                    <div id="menu-oceano" class="collapse <?= super_menu_in_out("oceano") ?>">
                        <ul class="menu-vertical">
                            <?php if (! $userDetails->missao && ! $userDetails->tripulacao["recrutando"] && $userDetails->navio) : ?>
                                <?= menu_link("oceano", "Ir para o oceano", "", "") ?>
                                <?= menu_link("amigaveis", "Batalhas Amigáveis", "", "") ?>
                            <?php endif; ?>
                            <?php if (! $userDetails->in_ilha) : ?>
                                <?= menu_link("servicoDenDen", "Vendas por correio", "", "") ?>
                            <?php endif; ?>
                            <?php if (! $userDetails->rotas
                                && ! $userDetails->missao && ! $userDetails->tripulacao["recrutando"]
                                && $userDetails->navio
                            ) : ?>
                                <?= menu_link("transporte", "Serviço de transporte", "", "") ?>
                            <?php endif; ?>
                        </ul>
                    </div>
                <?php endif; ?>
            <?php else : ?>
                <?= super_menu_link("combate", "menu-combate", "Combate", "combate", "combate") ?>
                <div id="menu-combate" class="collapse <?= super_menu_in_out("combate") ?>">
                    <ul class="menu-vertical">
                        <?= menu_link("combate", "Combate", "", "") ?>
                    </ul>
                </div>
            <?php endif; ?>
            <?= super_menu_link(
                "aliancaLista",
                "menu-alianca",
                $userDetails->tripulacao["faccao"] == FACCAO_PIRATA
                ? "Aliança"
                : "Frota",
                "alianca",
                $userDetails->tripulacao["faccao"] == FACCAO_PIRATA
                ? "alianca"
                : "frota", [SISTEMA_ALIANCAS]) ?>

            <div id="menu-alianca" class="collapse <?= super_menu_in_out("alianca") ?>">
                <ul class="menu-vertical">
                    <?php if (! $userDetails->ally) : ?>
                        <?= menu_link("aliancaCriar", "Juntar-se", "", "") ?>
                    <?php else : ?>
                        <?= menu_link("alianca", "Visão geral", "", "") ?>
                        <?= menu_link("aliancaDiplomacia", "Diplomacia", "", "") ?>
                        <?= menu_link("aliancaCooperacao", "Cooperação", "", "") ?>
                        <?= menu_link("aliancaMissoes", "Missões", "", "") ?>

                        <?php if ($userDetails->in_ilha) : ?>
                            <?= menu_link("aliancaBanco", "Banco da " . ($userDetails->tripulacao["faccao"] == FACCAO_MARINHA ? "Frota" : "Aliança"), "fa fa-archive", "") ?>
                        <?php endif; ?>
                    <?php endif; ?>
                    <?= menu_link("aliancaLista", "Frotas e Alianças", "", "") ?>
                </ul>
            </div>

            <?= super_menu_link("lojaEvento", "menu-events", "Eventos", "eventos", "eventos", [SISTEMA_EVENTOS]) ?>
            <div id="menu-events" class="collapse <?= super_menu_in_out("eventos") ?>">
                <ul class="menu-vertical">
                    <?= menu_link("lojaEvento", "Loja de Eventos", "", ""); ?>
                    <?= menu_link("eventoAnoNovo", "Evento de Ano Novo", "", "");  ?>
                    <?/*= menu_link("eventoNatal", "Evento de Natal", "fa fa-bolt", "");*/ ?>
                    <?/*= menu_link("eventoHalloween", "Semana do Terror", "fa fa-bolt", ""); */ ?>
                    <?/*= menu_link("eventoCriancas", "Semana das Crianças", "fa fa-bolt", ""); */ ?>
                    <?/*= menu_link("eventoIndependencia", "Evento da Independência", "fa fa-bolt", ""); */ ?>
                    <?/*= menu_link("eventoDiaPais", "Dia dos Pais", "fa fa-bolt", ""); */ ?>

                    <?php $evento_periodico_ativo = get_value_varchar_variavel_global(VARIAVEL_EVENTO_PERIODICO_ATIVO); ?>
                    <?php if ($evento_periodico_ativo == "eventoLadroesTesouro") : ?>
                        <?= menu_link("eventoLadroesTesouro", "Caça aos ladrões de tesouro", "", ""); ?>
                    <?php elseif ($evento_periodico_ativo == "eventoChefesIlhas") : ?>
                        <?= menu_link("eventoChefesIlhas", "Equilibrando os poderes do mundo", "", ""); ?>
                    <?php elseif ($evento_periodico_ativo == "boss") : ?>
                        <?= menu_link("boss", "Caça ao Chefão", "", ""); ?>
                    <?php elseif ($evento_periodico_ativo == "eventoPirata") : ?>
                        <?= menu_link("eventoPirata", "Caça aos Piratas", "", ""); ?>
                    <?php endif; ?>
                </ul>
            </div>
        <?php elseif ($userDetails->tripulacao && ! $userDetails->in_ilha) : ?>
            <?= super_menu_link("oceano", "menu-oceano", "Oceano", "oceano", "oceano", [SISTEMA_OCEANO]) ?>
            <div id="menu-oceano" class="collapse <?= super_menu_in_out("oceano") ?>">
                <ul class="menu-vertical">
                    <?= menu_link("respawn", "Tripulação Derrotada", "", "") ?>
                </ul>
            </div>
        <?php elseif ($userDetails->conta) : ?>
            <div id="menu-principal" class="collapse <?= super_menu_in_out("principal") ?>">
                <ul class="menu-vertical">
                    <?= menu_link("home", "Home", "fa fa-home", "Mantenha-se informado! Nunca se sabe a hora em que algo importante poderá acontecer.") ?>
                    <?= menu_link("seltrip", "Minhas Tripulações", "fa fa-users", "") ?>
                    <?= menu_link("#", "Logout", "fa fa-sign-out", "É hora de dar tchau!", "", "link_redirect", "link_Scripts/Geral/deslogar") ?>
                </ul>
            </div>
        <?php else : ?>
            <div id="menu-principal" class="collapse <?= super_menu_in_out("principal") ?>">
                <ul class="menu-vertical">
                    <?= menu_link("home", "Home", "fa fa-home", "Mantenha-se informado! Nunca se sabe a hora em que algo importante poderá acontecer.") ?>
                    <?= menu_link("cadastro", "Cadastrar", "", "") ?>
                    <?= menu_link("recuperarSenha", "Recuperar Senha", "", "") ?>
                    <?= menu_link("regras", "Regras e Punições", "", "") ?>
                    <?= menu_link("politica", "Política de Privacidade", "", "") ?>
                </ul>
            </div>
        <?php endif; ?>

        <?= super_menu_link("forum", "menu-forum", "Suporte & Fórum", "forum", "tutoriais") ?>
        <div id="menu-forum" class="collapse <?= super_menu_in_out("forum") ?>">
            <ul class="menu-vertical">
                <?php if ($userDetails->tripulacao) : ?>
                    <?= menu_link("forum", "Suporte & Fórum", "", "") ?>
                    <?php /*$categorias = $connection->run(
"SELECT *,
(SELECT count(*) FROM tb_forum_topico p WHERE p.categoria_id = c.id) AS topics,
(SELECT count(*) FROM tb_forum_topico p INNER JOIN tb_forum_topico_lido l ON p.id = l.topico_id AND l.tripulacao_id = ? WHERE p.categoria_id = c.id) AS topics_lidos
FROM tb_forum_categoria c ",
"i", array($userDetails->tripulacao["id"])); ?>
<?php while ($categoria = $categorias->fetch_array()): ?>
<?php $nao_lidos = $categoria["topics"] - $categoria["topics_lidos"]; ?>
<?php $badge = $nao_lidos ? " (" . ($categoria["topics"] - $categoria["topics_lidos"]) . ")" : ""; ?>
<?= menu_link("forumTopics&categoria=" . $categoria["id"], $categoria["nome"] . $badge, $categoria["icon"], "") ?>
<?php endwhile;*/ ?>
                <?php endif; ?>
                <?= menu_link("faq", "Base de Conhecimento", "", "") ?>
                <?= menu_link("https://fb.com/sugoigamebr", "Sugoi no Facebook", "", "", "", "", "", 'target="_blank"') ?>
                <?= menu_link("https://instagram.com/sugoigame", "Sugoi no Instagram", "", "", "", "", "", 'target="_blank"') ?>
            </ul>
        </div>

        <?php if ($userDetails->tripulacao['adm'] > 0) : ?>
            <?= super_menu_link("admin", "menu-admin", "Administração", "admin", "admin") ?>
            <div id="menu-admin" class="collapse <?= super_menu_in_out("admin") ?>">
                <ul class="menu-vertical">
                    <?= menu_link("admin-news", "Gerenciar Noticias", "fa fa-newspaper-o", "") ?>
                    <?= menu_link("admin-mails", "Gerenciar Den Den", "fa fa-envelope-o", "") ?>
                    <?= menu_link("admin-estatisticas", "Estatísticas", "fa fa-envelope-o", "") ?>
                    <?= menu_link("admin-combinacaoferreiro", "Combinações do Ferreiro", "fa fa-envelope-o", "") ?>
                    <?= menu_link("admin-combinacaoartesao", "Combinações do artesao", "fa fa-envelope-o", "") ?>
                    <?= menu_link("admin-combinacaocarpinteiro", "Combinações do carpinteiro", "fa fa-envelope-o", "") ?>
                    <?= menu_link("admin-combinacaoequips", "Equipamentos do jogo", "fa fa-envelope-o", "") ?>
                    <?= menu_link("admin-batalhas", "Log de Batalhas PvP", "fa fa-envelope-o", "") ?>
                    <?= menu_link("admin-reagents", "Reagents do jogo", "fa fa-envelope-o", "") ?>
                    <?= menu_link("admin-inserir-itens", "Inserir itens", "fa fa-envelope-o", "") ?>
                    <?= menu_link("admin-inserir-prof", "Inserir profissoes na ilha", "fa fa-envelope-o", "") ?>
                    <?= menu_link("admin-add-ouro", "Adicionar ouro", "fa fa-envelope-o", "") ?>
                    <?= menu_link("admin-beta", "Adicionar usuario beta", "fa fa-envelope-o", "") ?>
                    <?= menu_link("admin-add-beries", "Adicionar beries", "fa fa-envelope-o", "") ?>
                    <?= menu_link("admin-teleporte", "Teleportar Jogadores", "fa fa-envelope-o", "") ?>
                </ul>
            </div>
        <?php endif; ?>
    </div>
    
</div>

<?php if ($userDetails->conta) : ?>
    <div id="audio-position">
        <button class="btn btn-primary btn-blocks" id="audio-toggle">
        <script>
                var content = audioEnable
                ? '<i class="fa fa-volume-up" aria-hidden="true"></i> Som Ligado'
                : '<i class="fa fa-volume-off" aria-hidden="true"></i> Som Desligado';
            $("#audio-toggle").html(content);
        </script>
        </button>
    </div>
<?php endif; ?>