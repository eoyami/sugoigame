<?php
class Combate
{
    /**
     * @var mywrap_con
     */
    private $connection;

    /**
     * @var UserDetails
     */
    private $userDetails;

    /**
     * @var Protector
     */
    private $protector;

    /**
     * @var CombateLogger
     */
    public $logger;

    /**
     * Combate constructor.
     * @param $connection mywrap_con
     * @param $userDetails UserDetails
     * @param $protector Protector
     */
    public function __construct($connection, $userDetails, $protector)
    {
        $this->connection = $connection;
        $this->userDetails = $userDetails;
        $this->protector = $protector;
        $this->logger = new CombateLogger($connection, $userDetails);
    }

    public function get_my_id_index_in_pvp()
    {
        return $this->userDetails->tripulacao["id"] == $this->userDetails->combate_pvp["id_1"] ? 1 : 2;
    }

    public function get_enemy_id_index_in_pvp()
    {
        return $this->userDetails->tripulacao["id"] == $this->userDetails->combate_pvp["id_1"] ? 2 : 1;
    }

    public function pre_ataque(&$personagem_combate = null, $habilidade = null, $cod_skil = null, $tipo_skil = null)
    {
        if ($personagem_combate) {
            $this->aplica_buffs($personagem_combate);
        }

        $this->remove_buffs();

        $this->remove_espera();

        if ($personagem_combate && $habilidade && $cod_skil && $tipo_skil) {
            $this->insert_espera($personagem_combate, $cod_skil, $tipo_skil, $habilidade["espera"]);

            $this->aumenta_xp_profissao($personagem_combate, $tipo_skil);
        }

        $this->regen_mp();

        if ($this->userDetails->combate_pvp) {
            $this->muda_vez_pvp();
            if ($personagem_combate) {
                $this->aumenta_xp($personagem_combate);
            }
        } else if ($this->userDetails->combate_pve) {
            $this->muda_vez_pve();
        } else if ($this->userDetails->combate_bot) {
            $this->muda_vez_bot();
        }
    }

    public function pos_ataque()
    {

    }

    public function aplly_fadiga()
    {
        if (! $this->userDetails->combate_pvp) {
            return;
        }
        $personagens = get_pers_in_combate($this->userDetails->combate_pvp["id_" . $this->get_enemy_id_index_in_pvp()]);

        if (fadiga_batalha_ativa($personagens)) {
            foreach ($personagens as $pers) {
                if (is_tanque($pers)) {
                    $new_hp = max(1, $pers["hp"] - 200);
                    $this->connection->run("UPDATE tb_combate_personagens SET hp = ? WHERE cod = ?",
                        "ii", array($new_hp, $pers["cod"]));
                }
            }
        }
    }


    public function aplica_imunidade_special_effect($personagem_combate, $habilidade)
    {
    }

    public function has_special_effect($habilidade)
    {
        return ($this->userDetails->combate_pvp || $this->userDetails->combate_bot)
            && isset($habilidade["special_effect"])
            && $habilidade["special_effect"];
    }

    public function has_obstaculo()
    {
        return $this->userDetails->combate_pvp;
    }


    public function ataca_quadro(&$personagem_combate, $habilidade, $tipo_skil, &$alvo)
    {
        $relatorio_afetado = array();
        $relatorio_afetado["acerto"] = "1";

        if (! isset($alvo["obstaculo"])) {
            $this->aplica_buffs($alvo);
        }

        $relatorio_afetado["id"] = $alvo["tripulacao_id"];
        $relatorio_afetado["cod"] = $alvo["cod"];
        $relatorio_afetado["nome"] = $alvo["nome"];
        $relatorio_afetado["img"] = $alvo["img"];
        $relatorio_afetado["skin_r"] = $alvo["skin_r"];


        if ($tipo_skil == TIPO_SKILL_ATAQUE_CLASSE
            || $tipo_skil == TIPO_SKILL_ATAQUE_PROFISSAO
            || $tipo_skil == TIPO_SKILL_ATAQUE_AKUMA) {

            $resultado = calc_dano($personagem_combate, $alvo, $habilidade["dano"]);

            $relatorio_afetado["resultado"] = $resultado;

            $relatorio_afetado["tipo"] = 0;
            if ($resultado["esquivou"]) {
                $relatorio_afetado["esq"] = 1;
                if ($this->vale_fa($alvo)) {
                    $this->aumenta_fa_esq_bloq($alvo, $personagem_combate);
                }
            } else {
                if ($resultado["chance_esquiva"] - $alvo["haki_esq"] < 40
                    && $this->vale_fa($alvo)) {
                    $this->aumenta_fa_acerto_sem_agl($personagem_combate, $alvo);
                }
                if ($resultado["bloqueou"] && $this->vale_fa($alvo)) {
                    $this->aumenta_fa_esq_bloq($alvo, $personagem_combate);
                }
                if (! $resultado["bloqueou"]
                    && $resultado["chance_bloqueio"] - $alvo["haki_cri"] < 40
                    && $this->vale_fa($alvo)) {
                    $this->aumenta_fa_acerto_sem_agl($personagem_combate, $alvo);
                }
                if ($resultado["critou"] && $this->vale_fa($alvo)) {
                    $this->aumenta_fa_crit($personagem_combate, $alvo);
                }
                if (! $resultado["critou"]
                    && $resultado["chance_critico"] - $personagem_combate["haki_cri"] < 40
                    && $this->vale_fa($alvo)) {
                    $this->aumenta_fa_erro_crit($alvo, $personagem_combate);
                }

                $mod_akuma = $this->get_mod_akuma($personagem_combate, $alvo);

                $dano = (int) ($resultado["dano"] * $mod_akuma);

                if ($this->vale_fa($alvo)) {
                    $this->aumenta_fa_dano($dano, $personagem_combate, $alvo);
                    $this->aumenta_fa_absorv(
                        ($personagem_combate["atk"] > $alvo["def"] ? $alvo["def"] : $personagem_combate["atk"]) * 10,
                        $alvo,
                        $personagem_combate
                    );
                }

                //novo hp do alvo
                $nhp = max(0, $alvo["hp"] - $dano);

                if ($alvo["id"] == "bot") {
                    $this->connection->run("UPDATE tb_combate_personagens_bot SET hp = ? WHERE id = ?",
                        "ii", array($nhp, $alvo["bot_id"]));
                } else {
                    $this->connection->run("UPDATE tb_combate_personagens SET hp = ? WHERE cod = ?",
                        "ii", array($nhp, $alvo["cod"]));
                }
                if ($nhp <= 0) {
                    if ($alvo["id"] == "bot") {
                        $this->regen_mp_personagens_bot();
                    } else {
                        $this->regen_mp_personagens($alvo["id"]);
                    }

                    if ($this->userDetails->combate_pvp) {
                        $this->log_derrotado($alvo);
                    }
                }

                $relatorio_afetado["esq"] = 0;

                $relatorio_afetado["bloq"] = $resultado["bloqueou"] ? 1 : 0;
                $relatorio_afetado["cri"] = $resultado["critou"] ? 1 : 0;

                $relatorio_afetado["efeito"] = $dano;
                $relatorio_afetado["derrotado"] = $nhp ? 0 : 1;
            }
        } elseif ($tipo_skil == TIPO_SKILL_BUFF_CLASSE
            || $tipo_skil == TIPO_SKILL_BUFF_PROFISSAO
            || $tipo_skil == TIPO_SKILL_BUFF_AKUMA) {
            if (! isset($alvo["obstaculo"])) {
                if ($alvo["id"] == "bot") {
                    $this->connection->run(
                        "INSERT INTO tb_combate_buff_bot (id, cod, cod_buff, atr, efeito, espera)
                        VALUES (?, ?, ?, ?, ?, ?)",
                        "iiiiii", array(
                            $this->userDetails->combate_bot["id"],
                            $alvo["bot_id"],
                            $personagem_combate["cod"],
                            $habilidade["bonus_atr"],
                            $habilidade["bonus_atr_qnt"],
                            $habilidade["duracao"]));
                } else {
                    $this->connection->run(
                        "INSERT INTO tb_combate_buff (id, cod, cod_buff, atr, efeito, espera)
                        VALUES (?, ?, ?, ?, ?, ?)",
                        "iiiiii", array(
                            $alvo["id"],
                            $alvo["cod"],
                            $personagem_combate["cod"],
                            $habilidade["bonus_atr"],
                            $habilidade["bonus_atr_qnt"],
                            $habilidade["duracao"]));
                }

                $relatorio_afetado["tipo"] = 1;
                $relatorio_afetado["efeito"] = $habilidade["bonus_atr_qnt"];
                $relatorio_afetado["atributo"] = $habilidade["bonus_atr"];
            } else {
                $relatorio_afetado["tipo"] = 1;
                $relatorio_afetado["efeito"] = 0;
                $relatorio_afetado["atributo"] = $habilidade["bonus_atr"];
            }
        } elseif ($tipo_skil == TIPO_SKILL_MEDICAMENTO) {
            if (! isset($alvo["obstaculo"])) {
                $hp_recuperado = $habilidade["hp_recuperado"] * 10;
                $alvo["hp"] = min($alvo["hp_max"], $alvo["hp"] + $hp_recuperado);

                $relatorio_afetado["tipo"] = 2;
                $relatorio_afetado["cura_h"] = $hp_recuperado;
                $relatorio_afetado["cura_m"] = 0;

                if ($alvo["id"] == "bot") {
                    $this->connection->run("UPDATE tb_combate_personagens_bot SET hp = ? WHERE id = ?",
                        "ii", array($alvo["hp"], $alvo["bot_id"]));
                } else {
                    $this->connection->run("UPDATE tb_combate_personagens SET hp = ? WHERE cod = ?",
                        "ii", array($alvo["hp"], $alvo["cod"]));
                }
                $this->userDetails->reduz_item($habilidade["cod_remedio"], TIPO_ITEM_REMEDIO, 1);
            } else {
                $relatorio_afetado["tipo"] = 2;
                $relatorio_afetado["cura_h"] = 0;
                $relatorio_afetado["cura_m"] = 0;
            }
        }

        return $relatorio_afetado;
    }

    public function log_derrotado($pers)
    {
        if ($this->userDetails->combate_pvp) {
            $this->connection->run("INSERT INTO tb_combate_log_personagem_morto (combate, tripulacao_id, personagem_id) VALUE (?,?,?)",
                "iii", array($this->userDetails->combate_pvp["combate"], $pers["id"], $pers["cod"]));
        }
    }

    public function get_npc_status()
    {
        $npc_stats = array(
            "atk" => $this->userDetails->combate_pve["atk_npc"],
            "def" => $this->userDetails->combate_pve["def_npc"],
            "agl" => $this->userDetails->combate_pve["agl_npc"],
            "res" => $this->userDetails->combate_pve["res_npc"],
            "pre" => $this->userDetails->combate_pve["pre_npc"],
            "dex" => $this->userDetails->combate_pve["dex_npc"],
            "con" => $this->userDetails->combate_pve["con_npc"],
            "haki_esq" => 0,
            "haki_cri" => 0,
            "classe" => 0,
            "classe_score" => 0
        );
        return $npc_stats;
    }

    public function ataca_npc(&$personagem_combate, $habilidade, $tipo_skil, &$npc_stats)
    {
        $relatorio_afetado = array();
        $relatorio_afetado["id"] = atual_segundo();
        $relatorio_afetado["acerto"] = "1";
        $relatorio_afetado["quadro"] = "npc";
        $relatorio_afetado["cod"] = $this->userDetails->combate_pve["zona"];
        $relatorio_afetado["nome"] = $this->userDetails->combate_pve["nome_npc"];
        $relatorio_afetado["img"] = $this->userDetails->combate_pve["img_npc"];
        $relatorio_afetado["skin_r"] = "npc";

        if ($tipo_skil == TIPO_SKILL_ATAQUE_CLASSE
            || $tipo_skil == TIPO_SKILL_ATAQUE_PROFISSAO
            || $tipo_skil == TIPO_SKILL_ATAQUE_AKUMA) {

            $dano_habilidade = $habilidade["dano"] * 10;

            $resultado = calc_dano($personagem_combate, $npc_stats, $dano_habilidade);

            $relatorio_afetado["tipo"] = 0;
            if ($resultado["esquivou"]) {
                $relatorio_afetado["esq"] = "1";
            } else {
                if ($this->userDetails->combate_pve["boss_id"]) {
                    $resultado["dano"] = max($resultado["dano"], 2000);
                }

                if ($aumento = $this->userDetails->buffs->get_efeito("aumento_dano_causado_npc")) {
                    $resultado["dano"] += round($aumento * $resultado["dano"]);
                }

                $nhp = max(0, $this->userDetails->combate_pve["hp_npc"] - $resultado["dano"]);

                if (! $this->userDetails->combate_pve["boss_id"]) {
                    $this->connection->run("UPDATE tb_combate_npc SET hp_npc = ? WHERE id = ?",
                        "ii", array($nhp, $this->userDetails->tripulacao["id"]));
                } else {
                    $this->connection->run("UPDATE tb_boss SET hp = ? WHERE id = ?",
                        "ii", array($nhp, $this->userDetails->combate_pve["boss_id"]));

                    $this->registra_ataque_boss($resultado);
                }

                $relatorio_afetado["derrotado"] = $nhp ? 0 : 1;
                $relatorio_afetado["acerto"] = "1";
                $relatorio_afetado["esq"] = "0";
                $relatorio_afetado["bloq"] = $resultado["bloqueou"] ? 1 : 0;
                $relatorio_afetado["cri"] = $resultado["critou"] ? 1 : 0;
                $relatorio_afetado["efeito"] = $resultado["dano"];
                $this->userDetails->combate_pve["hp_npc"] = $nhp;
            }
        } else if ($tipo_skil == TIPO_SKILL_BUFF_CLASSE
            || $tipo_skil == TIPO_SKILL_BUFF_PROFISSAO
            || $tipo_skil == TIPO_SKILL_BUFF_AKUMA) {
            $this->connection->run("INSERT INTO tb_combate_buff_npc (tripulacao_id, atr, efeito, espera) VALUES (?, ?, ?, ?)",
                "iiii", array(
                    $this->userDetails->tripulacao["id"],
                    $habilidade["bonus_atr"],
                    $habilidade["bonus_atr_qnt"],
                    $habilidade["duracao"]));

            //relatorio buff
            $relatorio_afetado["tipo"] = 1;
            $relatorio_afetado["efeito"] = $habilidade["bonus_atr_qnt"];
            $relatorio_afetado["atributo"] = $habilidade["bonus_atr"];
        } else if ($tipo_skil == TIPO_SKILL_MEDICAMENTO) {
            $bonush = $habilidade["hp_recuperado"] * 10;
            $nhp = min($this->userDetails->combate_pve["hp_npc"] + $bonush, $this->userDetails->combate_pve["hp_max_npc"]);

            $this->connection->run("UPDATE tb_combate_npc SET hp_npc = ? WHERE id= ?",
                "ii", array($nhp, $this->userDetails->tripulacao["id"]));

            $relatorio_afetado["tipo"] = 2;
            $relatorio_afetado["cura_h"] = $bonush;
            $relatorio_afetado["cura_m"] = $habilidade["mp_recuperado"];

            $this->userDetails->reduz_item($habilidade["cod_remedio"], TIPO_ITEM_REMEDIO, 1);
        }
        return $relatorio_afetado;
    }

    function registra_ataque_boss($resultado)
    {
        $log = $this->connection->run("SELECT * FROM tb_boss_damage WHERE tripulacao_id = ?  AND real_boss_id = ?",
            "ii", array($this->userDetails->tripulacao["id"], $this->userDetails->combate_pve["real_boss_id"]));
        if ($log->count()) {
            $this->connection->run("UPDATE tb_boss_damage SET damage = damage + ? WHERE tripulacao_id = ?  AND real_boss_id = ?",
                "iii", array($resultado["dano"], $this->userDetails->tripulacao["id"], $this->userDetails->combate_pve["real_boss_id"]));
        } else {
            $this->connection->run("INSERT INTO tb_boss_damage (tripulacao_id, damage, real_boss_id) VALUES (?, ?, ?)",
                "iii", array($this->userDetails->tripulacao["id"], $resultado["dano"], $this->userDetails->combate_pve["real_boss_id"]));
        }

        if ($this->userDetails->ally) {
            $real_boss_id = $this->connection->run("SELECT real_boss_id FROM tb_boss WHERE id = ?",
                "i", array($this->userDetails->combate_pve["boss_id"]))->fetch_array()["real_boss_id"];
            $missao_ally_result = $this->connection->run("SELECT * FROM tb_alianca_missoes WHERE cod_alianca = ? AND boss_id = ?",
                "ii", array($this->userDetails->ally["cod_alianca"], $real_boss_id));

            if ($missao_ally_result->count()) {
                $missao_ally = $missao_ally_result->fetch_array();

                if ($missao_ally["quant"] < $missao_ally["fim"]) {
                    $this->connection->run("UPDATE tb_alianca_missoes SET quant = quant + ? WHERE cod_alianca = ?",
                        "ii", array($resultado["dano"], $this->userDetails->ally["cod_alianca"]));
                }
            }
        }
    }

    public function processa_turno_npc($tabuleiro)
    {
        $npc_stats = $this->get_npc_status();
        $this->remove_buffs_npc();
        $this->aplica_buffs_npc($npc_stats);

        $relatorio = $this->processa_ataque_npc($npc_stats, $tabuleiro);

        $this->logger->registra_turno_combate_pve($relatorio);

        $mira = $this->userDetails->combate_pve["mira"];
        if (rand(1, 100) < 20) {
            $mira = $this->get_mira_adjacente($mira);
        }

        $this->connection->run("UPDATE tb_combate_npc SET  mira = ? WHERE id = ?",
            "ii", array($mira, $this->userDetails->tripulacao["id"]));
    }

    public function get_effect_random()
    {
        $effects = array(
            "Atingir fisicamente",
            "Efeito básico",
            "Golpe de fogo",
            "Golpe de gelo",
            "Golpe de trovão",
            "Slash físico",
            "Garra física",
            "Especial físico 1"
        );

        return $effects[array_rand($effects)];
    }

    public function processa_ataque_npc(&$npc_stats, &$tabuleiro)
    {
        //turno do npc
        $relatorio = array();
        $relatorio_afetado = array();
        $relatorio["nome"] = $this->userDetails->combate_pve["nome_npc"];
        $relatorio["cod"] = "npc";
        $relatorio["img"] = $this->userDetails->combate_pve["img_npc"];
        $relatorio["skin_r"] = "npc";
        $relatorio["nome_skil"] = "Ataque";
        $relatorio["img_skil"] = rand(1, 100);
        $relatorio["descricao_skil"] = "";
        $relatorio["tipo"] = 1;
        $relatorio["effect"] = $this->get_effect_random();

        //sorteia um personagem
        $alvo_mira = $this->get_alvo_npc($this->userDetails->combate_pve["mira"], $tabuleiro);
        $alvo = $alvo_mira["alvo"];

        //sorteia uma skil
        $habilidade = \Regras\Habilidades::get_habilidade_aleatoria_nivel($alvo);

        $x = 0;
        $relatorio_afetado[$x] = $this->recebe_dano_npc($npc_stats, $habilidade, $alvo);
        $relatorio_afetado[$x]["quadro"] = $alvo_mira["x"] . "_" . $alvo_mira["y"];

        $relatorio["afetados"] = $relatorio_afetado;
        $relatorio["id"] = atual_segundo();

        return $relatorio;
    }

    public function get_alvo_npc($mira, $tabuleiro)
    {
        if ($mira < 0) {
            return $this->get_alvo_npc(0, $tabuleiro);
        } elseif ($mira > 4) {
            return $this->get_alvo_npc(4, $tabuleiro);
        }

        if (rand(1, 100) <= 90) {
            if (! isset($tabuleiro[$mira]) || ! count($tabuleiro[$mira])) {
                return $this->get_alvo_npc($this->get_mira_adjacente($mira), $tabuleiro);
            }

            $y_rand = array_rand($tabuleiro[$mira]);
            return array(
                "alvo" => $tabuleiro[$mira][$y_rand],
                "x" => $mira,
                "y" => $y_rand
            );
        } else {
            return $this->get_alvo_npc($this->get_mira_adjacente($mira), $tabuleiro);
        }
    }

    public function get_mira_adjacente($mira)
    {
        if ($mira >= 4) {
            return 3;
        } else if ($mira <= 0) {
            return 1;
        } else {
            return rand(1, 2) == 1 ? $mira - 1 : $mira + 1;
        }
    }

    public function recebe_dano_npc($npc_stats, $habilidade, &$alvo)
    {
        $relatorio_afetado = array();
        $relatorio_afetado["acerto"] = "1";
        $relatorio_afetado["id"] = atual_segundo();

        $this->aplica_buffs($alvo);

        $relatorio_afetado["cod"] = $alvo["cod"];
        $relatorio_afetado["nome"] = $alvo["nome"];
        $relatorio_afetado["img"] = $alvo["img"];
        $relatorio_afetado["skin_r"] = $alvo["skin_r"];
        $relatorio_afetado["tipo"] = 0;

        $dano_habilidade = $habilidade["dano"];

        $resultado = calc_dano($npc_stats, $alvo, $dano_habilidade);

        if ($resultado["esquivou"]) {
            $relatorio_afetado["esq"] = "1";
        } else {
            if ($reducao = $this->userDetails->buffs->get_efeito("reducao_dano_recebido_npc")) {
                $resultado["dano"] = max(0, $resultado["dano"] - round($reducao * $resultado["dano"]));
            }

            $nhp = max(0, $alvo["hp"] - $resultado["dano"]);

            $this->connection->run("UPDATE tb_combate_personagens SET hp = ? WHERE cod = ?",
                "ii", array($nhp, $alvo["cod"]));

            if ($nhp <= 0) {
                $this->regen_mp_personagens($this->userDetails->tripulacao["id"]);
            }

            $relatorio_afetado["esq"] = "0";
            $relatorio_afetado["bloq"] = $resultado["bloqueou"] ? 1 : 0;
            $relatorio_afetado["cri"] = $resultado["critou"] ? 1 : 0;
            $relatorio_afetado["tipo"] = 0;
            $relatorio_afetado["efeito"] = $resultado["dano"];
            $relatorio_afetado["derrotado"] = $nhp ? 0 : 1;
        }

        return $relatorio_afetado;
    }

    public function extract_quadros($quadro)
    {
        $quadros = explode(";", $quadro);

        foreach ($quadros as $index => $quadro) {
            if ($quadro == "npc") {
                $quadros[$index] = array(
                    "x" => "npc",
                    "y" => "npc",
                    "npc" => true
                );
            } else {
                $xy = explode("_", $quadro);
                $quadros[$index] = array(
                    "x" => $xy[0],
                    "y" => $xy[1],
                    "npc" => false
                );
            }
        }
        return $quadros;
    }

    public function load_personagem_combate($cod_pers)
    {
        $result = $this->connection->run("SELECT * FROM tb_combate_personagens WHERE id = ? AND cod = ?",
            "ii", array($this->userDetails->tripulacao["id"], $cod_pers));

        if (! $result->count()) {
            $this->protector->exit_error("Personagem inválido");
        }

        $personagem_combate = $result->fetch_array();
        if ($personagem_combate["hp"] <= 0) {
            $this->protector->exit_error("personagem impossibilitado de lutar");
        }

        $personagem = $this->userDetails->get_pers_by_cod($cod_pers, true);

        $personagem_combate["classe"] = $personagem["classe"];
        $personagem_combate["classe_score"] = $personagem["classe_score"];
        $personagem_combate["tripulacao_id"] = $personagem["id"];
        $personagem_combate["cod_capitao"] = $this->userDetails->capitao["cod"];

        return array_merge($personagem, $personagem_combate);
    }

    public function check_and_load_habilidade($personagem_combate, $cod_skil, $tipo_skil, $quadros)
    {
        $this->check_espera($personagem_combate, $cod_skil, $tipo_skil);
        $habilidade = $this->load_habilidade($personagem_combate, $cod_skil, $tipo_skil);

        if ($personagem_combate["mp"] < $habilidade["consumo"]) {
            $this->protector->exit_error("Vontade Insuficiente");
        }

        if (count($quadros) > $habilidade["area"]) {
            $this->protector->exit_error("Área exagerada");
        }

        return $habilidade;
    }

    public function load_habilidade($personagem, $cod_skil, $tipo_skil)
    {
        if ($tipo_skil == TIPO_SKILL_MEDICAMENTO) {
            if ($personagem["profissao"] != PROFISSAO_MEDICO) {
                $this->protector->exit_error("este personagem nao possui profissao adequada");
            }

            $result = $this->connection->run(
                "SELECT * FROM tb_usuario_itens itn
				 WHERE itn.id = ? AND itn.cod_item = ? AND itn.tipo_item = ?",
                "iii", array($this->userDetails->tripulacao["id"], $cod_skil, TIPO_ITEM_REMEDIO)
            );

            if ($result->count()) {
                $habilidade = $result->fetch_array();
                $habilidade = array_merge(
                    MapLoader::find("remedios", ["cod_remedio" => $habilidade["cod_item"]]),
                    $habilidade
                );
                $habilidade["consumo"] = $habilidade["requisito_lvl"] * 4;
                $habilidade["espera"] = 5;
                $habilidade["area"] = 1;
                $habilidade["alcance"] = 1;
                $habilidade["icon"] = $habilidade["img"];
                $habilidade["effect"] = "Cura 1";
                return $habilidade;
            } else {
                $this->protector->exit_error("Habilidade inválida");
            }
        } else {
            $table = get_skill_table($tipo_skil);

            $result = $this->connection->run(
                "SELECT * FROM tb_personagens_skil skil
				WHERE skil.cod_skil = ? AND skil.cod = ? AND skil.tipo = ?",
                "iii", array($cod_skil, $personagem["cod"], $tipo_skil)
            );

            if (! $result->count()) {
                $this->protector->exit_error("Habilidade inválida");
            }

            return array_merge(
                $result->fetch_array(),
                MapLoader::find($table, ["cod_skil" => $cod_skil])
            );
        }
    }

    public function check_espera($personagem, $cod_skil, $tipo_skil)
    {
        if ($tipo_skil == 10) {
            $result = $this->connection->run("SELECT * FROM tb_combate_skil_espera WHERE id = ? AND tipo = ?",
                "ii", array($this->userDetails->tripulacao["id"], $tipo_skil));
        } else {
            $result = $this->connection->run("SELECT * FROM tb_combate_skil_espera WHERE cod = ? AND cod_skil = ? AND tipo = ?",
                "iii", array($personagem["cod"], $cod_skil, $tipo_skil));
        }

        if ($result->count()) {
            $espera = $result->fetch_array();
            if ($espera["espera"] > 0) {
                $this->protector->exit_error("Skil em espera");
            }
        }
    }

    public function load_tabuleiro($id_1, $id_2 = null, $bot_id = null)
    {
        $personagens_combate = get_pers_in_combate($id_1);
        $tabuleiro = [];
        $this->_add_pers_tabuleiro($personagens_combate, $tabuleiro);
        if ($id_2) {
            $personagens_combate = get_pers_in_combate($id_2);
            $this->_add_pers_tabuleiro($personagens_combate, $tabuleiro);
        }
        if ($bot_id) {
            $personagens_combate = get_pers_bot_in_combate($bot_id);
            $this->_add_pers_tabuleiro($personagens_combate, $tabuleiro);
        }

        if ($this->has_obstaculo()) {
            $obstaculos = $this->connection->run("SELECT * FROM tb_obstaculos WHERE tripulacao_id = ? AND tipo = 1",
                "i", array($id_1))->fetch_all_array();
            foreach ($obstaculos as $obstaculo) {
                $tabuleiro[$obstaculo["x"]][$obstaculo["y"]] = obstaculo_para_tabuleiro($obstaculo);
            }

            $obstaculos = $this->connection->run("SELECT * FROM tb_obstaculos WHERE tripulacao_id = ? AND tipo = 2",
                "i", array($id_2))->fetch_all_array();
            foreach ($obstaculos as $obstaculo) {
                $tabuleiro[$obstaculo["x"]][$obstaculo["y"]] = obstaculo_para_tabuleiro($obstaculo);
            }
        }

        return $tabuleiro;
    }

    private function _add_pers_tabuleiro($personagens_combate, &$tabuleiro)
    {
        foreach ($personagens_combate as $pers) {
            if ($pers["hp"] > 0) {
                $tabuleiro[$pers["quadro_x"]][$pers["quadro_y"]] = $pers;
            }
        }
    }

    public function is_quadro_ataque_valido($personagem_combate, $quadro, $habilidade, $tabuleiro)
    {
        if ($quadro["x"] >= 10 || $quadro["x"] < 0
            || $quadro["y"] >= 20 && $quadro["y"] < 0
        ) {
            return false;
        }

        return true;

        // return $this->percorre_reta($personagem_combate, $quadro, $habilidade["alcance"], $tabuleiro, -1, -1)
        //     || $this->percorre_reta($personagem_combate, $quadro, $habilidade["alcance"], $tabuleiro, -1, 0)
        //     || $this->percorre_reta($personagem_combate, $quadro, $habilidade["alcance"], $tabuleiro, -1, 1)
        //     || $this->percorre_reta($personagem_combate, $quadro, $habilidade["alcance"], $tabuleiro, 0, -1)
        //     || $this->percorre_reta($personagem_combate, $quadro, $habilidade["alcance"], $tabuleiro, 0, 1)
        //     || $this->percorre_reta($personagem_combate, $quadro, $habilidade["alcance"], $tabuleiro, 1, -1)
        //     || $this->percorre_reta($personagem_combate, $quadro, $habilidade["alcance"], $tabuleiro, 1, 0)
        //     || $this->percorre_reta($personagem_combate, $quadro, $habilidade["alcance"], $tabuleiro, 1, 1);
    }

    private function percorre_reta($personagem_combate, $quadro, $alcance, $tabuleiro, $x, $y)
    {
        for ($i = 1; $i <= $alcance; $i++) {
            if ($quadro["x"] == $personagem_combate["quadro_x"] + ($i * $x)
                && $quadro["y"] == $personagem_combate["quadro_y"] + ($i * $y)
            ) {
                return true;
            }
            if (isset($tabuleiro[$i * $x])
                && isset($tabuleiro[$i * $x][$i * $y])
                && $tabuleiro[$i * $x][$i * $y]["hp"] > 0) {
                return false;
            }
        }
        return false;
    }

    public function is_area_valida($quadros)
    {
        for ($i = 1; $i < count($quadros); $i++) {
            $quadro = $quadros[$i];
            $quadro_anterior = $quadros[$i - 1];

            if (sqrt(pow($quadro["x"] - $quadro_anterior["x"], 2) + pow($quadro["y"] - $quadro_anterior["y"], 2)) > 1.5) {
                return false;
            }
        }
        return true;
    }

    public function perdeu_vez_pvp()
    {
        if ($this->userDetails->combate_pvp["vez_tempo"] < atual_segundo()) {
            $passe = "passe_" . $this->userDetails->combate_pvp["vez"];
            $this->connection->run("UPDATE tb_combate SET $passe = $passe + 1 WHERE combate = ?",
                "i", array($this->userDetails->combate_pvp["combate"]));


            $this->muda_vez_pvp();
        }
    }

    public function muda_vez_pvp()
    {
        $vez = $this->userDetails->combate_pvp["vez"] == 1 ? 2 : 1;
        $tempo = atual_segundo() + ($this->userDetails->combate_pvp["passe_$vez"] >= 3 ? 30 : 90);
        $this->connection->run("UPDATE tb_combate SET vez = ?, vez_tempo = ?, move_1 = ?, move_2 = ? WHERE combate = ?",
            "iiiii", array($vez, $tempo, 5, 5, $this->userDetails->combate_pvp["combate"]));
    }

    public function muda_vez_pve()
    {
        $this->connection->run("UPDATE tb_combate_npc SET move = 5 WHERE id = ?",
            "i", array($this->userDetails->tripulacao["id"]));
    }

    public function muda_vez_bot()
    {
        $vez = $this->userDetails->combate_bot["vez"] == 1 ? 2 : 1;
        $this->connection->run("UPDATE tb_combate_bot SET vez = ?, move = ? WHERE tripulacao_id = ?",
            "iii", array($vez, 5, $this->userDetails->tripulacao["id"]));
    }

    public function remove_espera()
    {
        $this->connection->run("UPDATE tb_combate_skil_espera SET espera = espera - 1 WHERE id = ?",
            "i", $this->userDetails->tripulacao["id"]);

        $this->connection->run("DELETE FROM tb_combate_skil_espera WHERE espera <= 0 AND id = ?",
            "i", $this->userDetails->tripulacao["id"]);
    }

    public function insert_espera($personagem, $cod_skil, $tipo_skil, $espera)
    {
        if ($espera) {
            $this->connection->run("INSERT INTO tb_combate_skil_espera (id, cod, cod_skil, tipo, espera) VALUES (?, ?, ?, ?, ?)",
                "iiiii", array($this->userDetails->tripulacao["id"], $personagem["cod"], $cod_skil, $tipo_skil, $espera));
        }
    }

    public function regen_mp()
    {
        if ($this->userDetails->combate_pvp) {
            $this->regen_mp_personagens($this->userDetails->combate_pvp["id_1"]);
            $this->regen_mp_personagens($this->userDetails->combate_pvp["id_2"]);
        } elseif ($this->userDetails->combate_pve) {
            $this->regen_mp_personagens($this->userDetails->tripulacao["id"]);
            $this->regen_mp_npc();
        } elseif ($this->userDetails->combate_bot) {
            $this->regen_mp_personagens($this->userDetails->tripulacao["id"]);
            $this->regen_mp_personagens_bot();
        }
    }
    public function regen_mp_personagens($id)
    {
        $this->connection->run("UPDATE tb_combate_personagens SET mp = mp + 1 WHERE id = ?",
            "i", $id);
    }
    public function regen_mp_personagens_bot()
    {
        $this->connection->run("UPDATE tb_combate_personagens_bot SET mp = mp + 1 WHERE combate_bot_id = ?",
            "i", $this->userDetails->combate_bot["id"]);
    }
    public function regen_mp_npc()
    {
        $this->connection->run("UPDATE tb_combate_npc SET mp_npc = mp_npc + 1 WHERE id = ?",
            "i", $this->userDetails->tripulacao["id"]);
    }

    public function reduz_mp($personagem, $quant)
    {
        $this->connection->run("UPDATE tb_combate_personagens SET mp = mp - ? WHERE cod = ?",
            "ii", array($quant, $personagem["cod"]));
    }

    public function remove_buffs()
    {
        $this->connection->run("UPDATE tb_combate_buff SET espera = espera - 1 WHERE id = ?",
            "i", $this->userDetails->tripulacao["id"]);

        $this->connection->run("DELETE FROM tb_combate_buff WHERE espera <= 0 AND id = ?",
            "i", $this->userDetails->tripulacao["id"]);
    }

    public function aumenta_xp_profissao($personagem, $tipo_skil)
    {
        if ($personagem["profissao"] == PROFISSAO_COMBATENTE
            || $personagem["profissao"] == PROFISSAO_MUSICO
            || $tipo_skil == 10
        ) {
            $this->connection->run(
                "UPDATE tb_personagens SET profissao_xp = profissao_xp + 1
				WHERE profissao_xp < profissao_xp_max AND cod = ?",
                "i", $personagem["cod"]
            );
        }
    }

    public function aumenta_xp($personagem)
    {
        $ip_1 = $this->connection->run("SELECT ip FROM tb_usuarios WHERE id = ?",
            "i", $this->userDetails->combate_pvp["id_1"])->fetch_array();
        $ip_2 = $this->connection->run("SELECT ip FROM tb_usuarios WHERE id = ?",
            "i", $this->userDetails->combate_pvp["id_2"])->fetch_array();

        $xp = $ip_1["ip"] == $ip_2["ip"] ? 10 : 40;

        $this->connection->run("UPDATE tb_personagens SET xp = xp + ? WHERE cod = ?",
            "ii", array($xp, $personagem["cod"]));
    }

    public function aplica_buffs(&$personagem)
    {
        $buffs = $this->connection->run("SELECT * FROM tb_combate_buff WHERE cod = ?",
            "i", $personagem["cod"])->fetch_all_array();

        foreach ($buffs as $buff) {
            $atr = nome_atributo_tabela($buff["atr"]);
            $personagem[$atr] += $buff["efeito"];
        }

        for ($i = 1; $i <= 8; $i++) {
            $atr = nome_atributo_tabela($i);
            $personagem[$atr] = max(1, $personagem[$atr]);
        }
    }

    public function aplica_buffs_npc(&$npc_atr)
    {
        $buffs = $this->connection->run("SELECT * FROM tb_combate_buff_npc WHERE tripulacao_id = ?",
            "i", $this->userDetails->tripulacao["id"])->fetch_all_array();

        foreach ($buffs as $buff) {
            $atr = nome_atributo_tabela($buff["atr"]);
            $npc_atr[$atr] += $buff["efeito"];
        }

        for ($i = 1; $i <= 7; $i++) {
            $atr = nome_atributo_tabela($i);
            $npc_atr[$atr] = max(1, $npc_atr[$atr]);
        }
    }

    public function remove_buffs_npc()
    {
        $this->connection->run("UPDATE tb_combate_buff_npc SET espera = espera - 1 WHERE tripulacao_id = ?",
            "i", $this->userDetails->tripulacao["id"]);

        $this->connection->run("DELETE FROM tb_combate_buff_npc WHERE espera <= 0 AND tripulacao_id = ?",
            "i", $this->userDetails->tripulacao["id"]);
    }
}
