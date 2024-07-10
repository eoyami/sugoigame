<?php
include "../../../Includes/conectdb.php";
$protector->need_tripulacao();
$pers_cod = $protector->get_number_or_exit("cod");

$pers = $userDetails->get_pers_by_cod($pers_cod);

if (! $pers) {
    $protector->exit_error("Personagem inválido");
}
?>

<?php function ordena_receitas($receitas, $aleatorios)
{
    foreach ($receitas as $index => $receita) {
        if ($receita["aleatorio"]) {
            $receitas[$index]["resultados"] = array();
            foreach ($aleatorios as $aleatorio) {
                if ($aleatorio["receita"] == $receita["cod_receita"]) {
                    $receitas[$index]["resultados"][] = $aleatorio;
                }
            }
        }
    }
    return $receitas;
} ?>

<?php function render_item($cod, $tipo, $quant, $materiais, $equipamentos)
{ ?>
    <?php if ($tipo == TIPO_ITEM_REAGENT) : ?>
        <button class="inventario-item equipamentos_classe_1" data-content="-" data-toggle="popover" data-html="true"
            data-placement="bottom" data-trigger="focus"
            data-template='<div class="inventario-item-info"><?= $materiais[$cod]["nome"] ?></div>'>
            <?= get_img_item($materiais[$cod]) ?>
            <span class="badge badge-default">
                <?= $quant ?>
            </span>
        </button>
    <?php elseif ($tipo == TIPO_ITEM_EQUIPAMENTO) : ?>
        <button class="inventario-item equipamentos_classe_<?= $equipamentos[$cod]["categoria"] ?>" data-content="-"
            data-toggle="popover" data-html="true" data-placement="bottom" data-trigger="focus"
            data-template='<div class="inventario-item-info"><?= $equipamentos[$cod]["nome"] ?> - <?= nome_slot($equipamentos[$cod]["slot"]) ?> - Nível <?= $equipamentos[$cod]["lvl"] ?> (<?= nome_atributo($equipamentos[$cod]["b_1"]) . " e " . nome_atributo($equipamentos[$cod]["b_2"]) ?>)</div>'>
            <img src="Imagens/Itens/<?= $equipamentos[$cod]["img"] ?>.png" /><br />
            <span class="badge badge-default">
                <?= $quant ?>
            </span>
        </button>
    <?php endif; ?>
<?php } ?>

<?php render_personagem_sub_panel_with_img_top($pers); ?>
<div class="panel-body">
    <?php render_painel_profissao($pers); ?>
</div>
<?php render_personagem_sub_panel_with_img_bottom(); ?>

<?php if ($pers["profissao"] == PROFISSAO_MEDICO) : ?>
    <?php $items = MapLoader::filter("remedios", function ($remedio) {
        return $remedio["requisito_lvl"] != 0;
    }); ?>

    <div class="row">
        <?php foreach ($items as $item) : ?>
            <?php $preco = (($item["hp_recuperado"] + $item["mp_recuperado"]) * 60) * (1 - $pers["profissao_lvl"] * 0.05); ?>
            <div class="panel panel-default col-xs-4 h-100 m0">
                <div class="panel-body">
                    <?= info_item_with_img($item, $item, FALSE, FALSE, FALSE) ?>
                    <p>
                        Nível de profissão necessário:
                        <?= $item["requisito_lvl"]; ?>
                    </p>
                    <div>
                        <img src="Imagens/Icones/Berries.png" />
                        <?= mascara_berries($preco) ?>
                    </div>
                </div>
                <div class="panel-footer">
                    <?php if ($userDetails->tripulacao["berries"] >= $preco && $pers["profissao_lvl"] >= $item["requisito_lvl"]) : ?>
                        <form class="form-inline ajax_form" action="Profissao/medico_criar_remedio" method="post"
                            data-question="Deseja mesmo criar este item?">
                            <input type="hidden" name="pers" value="<?= $pers["cod"]; ?>" />
                            <input type="hidden" name="item" value="<?= $item["cod_remedio"]; ?>" />
                            <input placeholder="Insira a quantidade desejada" class="form-control w-50" size="4" min="1"
                                type="number" max="<?= floor($userDetails->tripulacao["berries"] / $preco) ?>" name="quant"
                                value="1" type="number">
                            <button type="submit" class="btn btn-success">
                                Criar
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
<?php if ($pers["profissao"] == PROFISSAO_COZINHEIRO) : ?>
    <?php $items = MapLoader::filter("comidas", function ($remedio) {
        return $remedio["requisito_lvl"] != 0;
    }); ?>

    <div class="row">
        <?php foreach ($items as $item) : ?>
            <?php $preco = (($item["hp_recuperado"] + $item["mp_recuperado"]) * 50) * (1 - $pers["profissao_lvl"] * 0.05); ?>
            <div class="panel panel-default col-xs-4 col-md-3 h-100 m0">
                <div class="panel-body">
                    <?= info_item_with_img($item, $item, FALSE, FALSE, FALSE) ?>
                    <p>
                        Nível de profissão necessário:
                        <?= $item["requisito_lvl"]; ?>
                    </p>
                    <div>
                        <img src="Imagens/Icones/Berries.png" />
                        <?= mascara_berries($preco) ?>
                    </div>
                </div>
                <div class="panel-footer">
                    <?php if ($userDetails->tripulacao["berries"] >= $preco && $pers["profissao_lvl"] >= $item["requisito_lvl"]) : ?>
                        <form class="form-inline ajax_form" action="Profissao/cozinheiro_criar_comida" method="post"
                            data-question="Deseja mesmo criar este item?">
                            <input type="hidden" name="pers" value="<?= $pers["cod"]; ?>" />
                            <input type="hidden" name="item" value="<?= $item["cod_comida"]; ?>" />
                            <input placeholder="Insira a quantidade desejada" class="form-control" size="4" min="1" type="number"
                                max="<?= floor($userDetails->tripulacao["berries"] / $preco) ?>" name="quant" value="1"
                                type="number">
                            <button type="submit" class="btn btn-success">
                                Criar
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
<?php if ($pers["profissao"] == PROFISSAO_FERREIRO
    || $pers["profissao"] == PROFISSAO_CARPINTEIRO
    || $pers["profissao"] == PROFISSAO_ARTESAO
) : ?>
    <?php $receitas_ferreiro = $connection->run(
        "SELECT * FROM tb_combinacoes_forja c
         LEFT JOIN tb_combinacoes_forja_conhecidas con ON c.cod_receita = con.combinacao_id AND con.tripulacao_id = ?
         WHERE c.visivel = 1 OR con.id IS NOT NULL
         ORDER BY c.lvl, c.tipo DESC, c.cod",
        "i", array($userDetails->tripulacao["id"])
    )->fetch_all_array(); ?>
    <?php $receitas_ferreiro_aleatorio = $connection->run("SELECT * FROM tb_combinacoes_forja_aleatorio")->fetch_all_array(); ?>
    <?php $receitas_ferreiro = ordena_receitas($receitas_ferreiro, $receitas_ferreiro_aleatorio); ?>

    <?php $receitas_artesao = $connection->run(
        "SELECT * FROM tb_combinacoes_artesao c
         LEFT JOIN tb_combinacoes_artesao_conhecidas con ON c.cod_receita = con.combinacao_id AND con.tripulacao_id = ?
         WHERE c.visivel = 1 OR con.id IS NOT NULL
         ORDER BY c.lvl, c.tipo DESC, c.cod",
        "i", array($userDetails->tripulacao["id"])
    )->fetch_all_array(); ?>
    <?php $receitas_artesao_aleatorio = $connection->run("SELECT * FROM tb_combinacoes_artesao_aleatorio")->fetch_all_array(); ?>
    <?php $receitas_artesao = ordena_receitas($receitas_artesao, $receitas_artesao_aleatorio); ?>

    <?php $receitas_carpinteiro = $connection->run(
        "SELECT * FROM tb_combinacoes_carpinteiro c
         LEFT JOIN tb_combinacoes_carpinteiro_conhecidas con ON c.cod_receita = con.combinacao_id AND con.tripulacao_id = ?
         WHERE c.visivel = 1 OR con.id IS NOT NULL
         ORDER BY c.lvl, c.tipo DESC, c.cod",
        "i", array($userDetails->tripulacao["id"])
    )->fetch_all_array(); ?>
    <?php $receitas_carpinteiro_aleatorio = $connection->run("SELECT * FROM tb_combinacoes_carpinteiro_aleatorio")->fetch_all_array(); ?>
    <?php $receitas_carpinteiro = ordena_receitas($receitas_carpinteiro, $receitas_carpinteiro_aleatorio); ?>

    <?php $materiais_bd = $connection->run("SELECT * FROM tb_item_reagents")->fetch_all_array(); ?>
    <?php $equipamentos_bd = $connection->run("SELECT * FROM tb_equipamentos")->fetch_all_array(); ?>

    <?php
    $materiais = array();
    foreach ($materiais_bd as $material) {
        $materiais[$material["cod_reagent"]] = $material;
    }
    $equipamentos = array();
    foreach ($equipamentos_bd as $equip) {
        $equipamentos[$equip["item"]] = $equip;
    }
    ?>

    <?php $receitas = $pers["profissao"] == PROFISSAO_FERREIRO
        ? $receitas_ferreiro
        : ($pers["profissao"] == PROFISSAO_CARPINTEIRO
            ? $receitas_carpinteiro
            : $receitas_artesao); ?>

    <h3>Receitas disponíveis:</h3>
    <ul class="list-group">
        <?php foreach ($receitas as $receita) : ?>
            <?php if ($receita["lvl"] <= $pers["profissao_lvl"]) : ?>
                <li class="list-group-item">
                    <h4>Nível
                        <?= $receita["lvl"] ?>
                    </h4>
                    <div class="row">
                        <div class="col-md-6">
                            <h4>Itens necessários:</h4>
                            <div class="row">
                                <?php for ($i = 1; $i <= 8; $i++) : ?>
                                    <?php if ($receita[$i]) : ?>
                                        <div style="display: inline-block;">
                                            <p>Espaço
                                                <?= $i ?>:
                                            </p>
                                            <?php render_item($receita[$i], $receita[$i . "_t"], $receita[$i . "_q"], $materiais, $equipamentos); ?>
                                        </div>
                                    <?php endif; ?>
                                <?php endfor; ?>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <h4>Resultados possíveis:</h4>
                            <div class="row">
                                <?php if ($receita["aleatorio"]) : ?>
                                    <?php foreach ($receita["resultados"] as $resultado) : ?>
                                        <?php render_item($resultado["cod"], $resultado["tipo"], $resultado["quant"], $materiais, $equipamentos); ?>
                                    <?php endforeach; ?>
                                <?php else : ?>
                                    <?php render_item($receita["cod"], $receita["tipo"], $receita["quant"], $materiais, $equipamentos); ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </li>
            <?php endif; ?>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>

