# Chino · los clips que faltan (y por qué aquí no son opcionales)

El banco de chino ya siembra y se sirve sin audio: 15 lecciones y 62 ítems sobre
los mismos diez descriptores que italiano, francés y alemán. Pero **el chino es la
lengua que más necesita los clips**: `mā má mǎ mà` son cuatro palabras y sobre el
papel son la misma sílaba. Un alumno puede aprender el pinyin y los caracteres
leyendo; los tonos, no. Por eso este fichero es la primera prioridad de grabación
de las cuatro lenguas, por delante de `U1-audio-pendiente.md` (italiano) y de
`interlocutor-clips-pendientes.md`.

**Dónde van los ficheros:** `database/data/audio-lenguas/zh/u<N>/<clave>.mp3`
(también valen `.ogg` y `.m4a`). El sembrador calcula el hash, lo publica en el
almacén y sustituye la ruta. **Quien escribe el banco nunca escribe un hash.**

**Quién los graba:** un hablante nativo de chino estándar (pǔtōnghuà), o el
profesor de chino. Despacio, con los tonos completos y sin música de fondo; en la
lista de vocabulario, una pausa de un segundo entre palabra y palabra, y cada
palabra **dos veces**. Los diálogos, a dos voces (un chico y una chica).

---

## Regla general: tres clips por unidad

| Clave | Contenido | Duración |
|---|---|---|
| `zh/uN/cihui` | La lista de vocabulario de la unidad (17 palabras, cada una dos veces) | ~60 s |
| `zh/uN/escena` | El diálogo o el texto modelo de la lección de la unidad | ~30 s |
| `zh/uN/tingxie-1` | Una frase corta para dictado (pinyin con tonos) | ~4 s |

Más, en U1, U2, U4, U5 y U7, **un clip de pronunciación** con la regla de la unidad
(`zh/uN/fayin`). Son los que sustituyen a la explicación escrita de «cómo suena».

---

## U1 · 你好

| Clave | Guion exacto |
|---|---|
| `zh/u1/fayin` | mā · má · mǎ · mà · ma — (pausa) — nǐ · hǎo · nǐ hǎo — shì · jiào · rén — xièxie · qǐng · Zhōngguó · zàijiàn · cèsuǒ · rén · sì · shì |
| `zh/u1/cihui` | 你好 nǐ hǎo · 您好 nín hǎo · 老师好 lǎoshī hǎo · 再见 zàijiàn · 谢谢 xièxie · 不客气 bú kèqi · 对不起 duìbuqǐ · 没关系 méi guānxi · 请 qǐng · 是 shì · 不是 bú shì · 我 wǒ · 你 nǐ · 他 tā · 她 tā · 叫 jiào · 中国 Zhōngguó · 厄瓜多尔 Èguāduō'ěr · 人 rén · 学生 xuésheng · 老师 lǎoshī |
| `zh/u1/escena` | El diálogo 第一天 completo (abajo) |
| `zh/u1/tingxie-1` | 我是厄瓜多尔人。 Wǒ shì Èguāduō'ěr rén. |

**`zh/u1/escena`, guion literal:**

> **李明** — 你好！我叫李明。你叫什么名字？
> **Sofía** — 你好，李明。我叫 Sofía。
> **李明** — 你是哪国人？
> **Sofía** — 我是厄瓜多尔人。你呢？
> **李明** — 我是中国人，北京人。你是学生吗？
> **Sofía** — 是。对不起……她是谁？
> **李明** — 她是王老师。
> **Sofía** — 谢谢，李明。再见！
> **李明** — 再见！

## U2 · 我的家

| Clave | Guion exacto |
|---|---|
| `zh/u2/fayin` | nǐ hǎo (dicho «ní hǎo») · wǒ yǒu · hěn hǎo · wǔ gè — bù chī · bú shì · bù hǎo · bú qù — yī · yí gè · yì tiān · yì nián |
| `zh/u2/cihui` | 家 jiā · 爸爸 bàba · 妈妈 māma · 哥哥 gēge · 弟弟 dìdi · 姐姐 jiějie · 妹妹 mèimei · 有 yǒu · 没有 méiyǒu · 个 gè · 口 kǒu · 几 jǐ · 岁 suì · 的 de · 朋友 péngyou · 狗 gǒu · 多大 duō dà — y los números 一 a 十 |
| `zh/u2/escena` | 我家有四口人：爸爸、妈妈、姐姐和我。我没有哥哥。我姐姐十八岁，我十五岁。我有一个狗，叫小白。 |
| `zh/u2/tingxie-1` | 我家有四口人。 Wǒ jiā yǒu sì kǒu rén. |

## U3 · 我的一天

| Clave | Guion exacto |
|---|---|
| `zh/u3/cihui` | 起床 qǐchuáng · 吃饭 chī fàn · 早饭 zǎofàn · 午饭 wǔfàn · 晚饭 wǎnfàn · 上课 shàngkè · 学习 xuéxí · 看书 kàn shū · 看电视 kàn diànshì · 睡觉 shuìjiào · 在 zài · 学校 xuéxiào · 现在 xiànzài · 每天 měitiān · 回家 huí jiā · 点 diǎn · 半 bàn · 分 fēn · 两 liǎng · 上午 shàngwǔ · 下午 xiàwǔ · 晚上 wǎnshang |
| `zh/u3/escena` | 我每天六点起床。我七点吃早饭。我上午在学校上课。我下午三点回家。晚上我看书，十点睡觉。 |
| `zh/u3/tingxie-1` | 现在三点半。 Xiànzài sān diǎn bàn. |

## U4 · 我喜欢

| Clave | Guion exacto |
|---|---|
| `zh/u4/fayin` | zài / zhài · cài / chài · sān / shān · sì / shì · zì / zhì — (cada pareja dos veces) |
| `zh/u4/cihui` | 喜欢 xǐhuan · 也 yě · 都 dōu · 音乐 yīnyuè · 电影 diànyǐng · 足球 zúqiú · 中文 Zhōngwén · 水果 shuǐguǒ · 苹果 píngguǒ · 茶 chá · 咖啡 kāfēi · 猫 māo · 很 hěn · 好吃 hǎochī · 好看 hǎokàn · 大 dà · 小 xiǎo · 今天 jīntiān · 明天 míngtiān · 来 lái · 去 qù |
| `zh/u4/escena` | — 你喜欢什么？ — 我喜欢音乐和足球。 — 你喜欢咖啡吗？ — 不喜欢，我喜欢茶。 — 我也喜欢茶。茶很好喝。 |
| `zh/u4/tingxie-1` | 我也喜欢茶。 Wǒ yě xǐhuan chá. |

## U5 · 在哪儿

| Clave | Guion exacto |
|---|---|
| `zh/u5/fayin` | qián / qiáng · yīn / yīng · shēn / shēng · fàn / fàng · bàn / bàng — nǎr · zhèr · nàr |
| `zh/u5/cihui` | 哪儿 nǎr · 这儿 zhèr · 那儿 nàr · 商店 shāngdiàn · 医院 yīyuàn · 饭馆 fànguǎn · 火车站 huǒchēzhàn · 银行 yínháng · 公园 gōngyuán · 前面 qiánmiàn · 后面 hòumiàn · 旁边 pángbiān · 左边 zuǒbian · 右边 yòubian · 走 zǒu · 远 yuǎn · 近 jìn · 请问 qǐngwèn · 城市 chéngshì · 山 shān · 漂亮 piàoliang · 多 duō |
| `zh/u5/escena` | — 请问，医院在哪儿？ — 医院在学校旁边。 — 远吗？ — 不远，很近。 (pausa) 我的城市叫基多。基多不大，但是很漂亮。基多有很多山。 |
| `zh/u5/tingxie-1` | 医院在学校旁边。 Yīyuàn zài xuéxiào pángbiān. |

## U6 · 在饭馆

| Clave | Guion exacto |
|---|---|
| `zh/u6/fayin` | nǚ / nǔ · lǜ / lù · qù · yú · xué · fúwùyuán |
| `zh/u6/cihui` | 要 yào · 想 xiǎng · 吃 chī · 喝 hē · 服务员 fúwùyuán · 菜单 càidān · 米饭 mǐfàn · 面条 miàntiáo · 鸡 jī · 鱼 yú · 菜 cài · 水 shuǐ · 果汁 guǒzhī · 碗 wǎn · 杯 bēi · 多少钱 duōshao qián · 块 kuài · 买单 mǎidān · 出口 chūkǒu · 入口 rùkǒu · 男 nán · 女 nǚ · 开 kāi · 关 guān |
| `zh/u6/escena` | — 服务员！ — 你要什么？ — 我要一碗面条和一杯茶。 — 好的。 (pausa) — 多少钱？ — 二十五块。 |
| `zh/u6/tingxie-1` | 我要一杯茶。 Wǒ yào yì bēi chá. |

## U7 · 这个多少钱

| Clave | Guion exacto |
|---|---|
| `zh/u7/fayin` | yīfu · piányi · zěnmeyàng · bàba · xièxie · shénme — yìdiǎnr · nǎr · zhèr · wánr |
| `zh/u7/cihui` | 这个 zhège · 那个 nàge · 衣服 yīfu · 件 jiàn · 买 mǎi · 卖 mài · 贵 guì · 便宜 piányi · 太…了 tài… le · 一点儿 yìdiǎnr · 红 hóng · 白 bái · 天气 tiānqì · 冷 lěng · 热 rè · 下雨 xià yǔ · 怎么样 zěnmeyàng · 一百 yìbǎi |
| `zh/u7/escena` | — 这件衣服多少钱？ — 一百二十块。 — 太贵了！便宜一点儿吧。 — 那个八十块。 — 好，我买那个。 (pausa) — 今天天气怎么样？ — 今天很冷。明天不下雨，明天很热。 |
| `zh/u7/tingxie-1` | 太贵了！ Tài guì le! |

## U8 · 昨天

| Clave | Guion exacto |
|---|---|
| `zh/u8/cihui` | 昨天 zuótiān · 了 le · 没 méi · 看 kàn · 做 zuò · 玩儿 wánr · 累 lèi · 高兴 gāoxìng · 作业 zuòyè · 周末 zhōumò · 星期六 xīngqīliù · 星期天 xīngqītiān |
| `zh/u8/escena` | 昨天我去了商店，买了一件衣服。下午我和朋友看了一个电影。晚上我没做作业，太累了。 |
| `zh/u8/tingxie-1` | 昨天我去了公园。 Zuótiān wǒ qù le gōngyuán. |

## U9 · 我听不懂

| Clave | Guion exacto |
|---|---|
| `zh/u9/cihui` | 我听不懂 wǒ tīng bu dǒng · 我看不懂 wǒ kàn bu dǒng · 请再说一遍 qǐng zài shuō yí biàn · 请说慢一点儿 qǐng shuō màn yìdiǎnr · 用中文怎么说 yòng Zhōngwén zěnme shuō · 什么意思 shénme yìsi · 我知道 wǒ zhīdào · 我不知道 wǒ bù zhīdào · 对 duì · 没问题 méi wèntí |
| `zh/u9/escena` | El diálogo con 王老师 de la lección `wo-ting-bu-dong`, completo |
| `zh/u9/tingxie-1` | 请再说一遍。 Qǐng zài shuō yí biàn. |

---

## Lo que se añade al banco cuando los clips existan

### 1 · Bloques de audio en las lecciones

En cada lección de unidad, después de la lista de vocabulario:

```php
['tipo' => 'audio', 'clip' => 'zh/u1/cihui', 'duracion_s' => 60,
 'texto' => ['zh' => '你好 · 您好 · 老师好 · 再见 · 谢谢 · 不客气 · 对不起 · 没关系 · 请 · 是 · 不是 · 我 · 你 · 他 · 她 · 叫 · 中国 · 厄瓜多尔 · 人 · 学生 · 老师',
             'es' => 'Las palabras de la unidad, cada una dos veces.']],
```

En `ni-hao` (A1.CO.2), justo después del aviso «LOS TONOS SON PARTE DE LA PALABRA»:

```php
['tipo' => 'audio', 'clip' => 'zh/u1/fayin', 'duracion_s' => 25,
 'texto' => ['zh' => 'mā · má · mǎ · mà · ma — nǐ hǎo — shì · jiào · rén — xièxie · qǐng · Zhōngguó',
             'es' => 'Los cuatro tonos y el neutro sobre «ma», y las letras que engañan.']],
```

### 2 · Los ítems de comprensión oral (A1.CO.1, A1.CO.2, A1.CO.3)

Los tres descriptores de comprensión oral de chino no tienen ítems hasta que
haya clips —igual que en las otras tres lenguas—. Estos son los que entran, dos
por descriptor como mínimo (`MasteryTracker::ITEMS_TO_MASTER = 2`):

```php
// A1.CO.2 — saludos y fórmulas (U1)
['tipo' => 'escucha', 'descriptor' => 'A1.CO.2', 'lengua' => 'zh', 'seq' => 1,
 'clip' => 'zh/u1/tingxie-1', 'transcripcion' => ['zh' => '我是厄瓜多尔人。'],
 'consigna' => ['es' => 'Escucha. ¿De dónde es quien habla?'],
 'opciones' => [['clave' => 'a', 'texto' => ['es' => 'De Ecuador']], ['clave' => 'b', 'texto' => ['es' => 'De China']], ['clave' => 'c', 'texto' => ['es' => 'De Pekín']]],
 'correcta' => 'a'],
['tipo' => 'dictado', 'descriptor' => 'A1.CO.2', 'lengua' => 'zh', 'seq' => 2,
 'clip' => 'zh/u1/tingxie-1', 'transcripcion' => ['zh' => 'Wǒ shì Èguāduō’ěr rén.'],
 'consigna' => ['es' => 'Escucha y escribe la frase en pinyin, con los tonos.'],
 'aceptadas' => ['wǒ shì èguāduō’ěr rén', 'wo3 shi4 e4gua1duo1er3 ren2', '我是厄瓜多尔人']],

// A1.CO.3 — la hora y la rutina (U3)
['tipo' => 'dictado', 'descriptor' => 'A1.CO.3', 'lengua' => 'zh', 'seq' => 1,
 'clip' => 'zh/u3/tingxie-1', 'transcripcion' => ['zh' => 'Xiànzài sān diǎn bàn.'],
 'consigna' => ['es' => 'Escucha y escribe la hora en pinyin, con los tonos.'],
 'aceptadas' => ['xiànzài sān diǎn bàn', 'xian4zai4 san1 dian3 ban4', '现在三点半']],
['tipo' => 'escucha', 'descriptor' => 'A1.CO.3', 'lengua' => 'zh', 'seq' => 2,
 'clip' => 'zh/u3/escena', 'transcripcion' => ['zh' => '我每天六点起床。我七点吃早饭。我上午在学校上课。我下午三点回家。晚上我看书，十点睡觉。'],
 'consigna' => ['es' => 'Escucha el día de Sofía. ¿A qué hora vuelve a casa?'],
 'opciones' => [['clave' => 'a', 'texto' => ['es' => 'A las tres de la tarde']], ['clave' => 'b', 'texto' => ['es' => 'A las seis de la mañana']], ['clave' => 'c', 'texto' => ['es' => 'A las diez de la noche']]],
 'correcta' => 'a'],

// A1.CO.1 — instrucciones sencillas (U5, U9)
['tipo' => 'escucha', 'descriptor' => 'A1.CO.1', 'lengua' => 'zh', 'seq' => 1,
 'clip' => 'zh/u5/tingxie-1', 'transcripcion' => ['zh' => '医院在学校旁边。'],
 'consigna' => ['es' => 'Escucha. ¿Dónde está el hospital?'],
 'opciones' => [['clave' => 'a', 'texto' => ['es' => 'Al lado de la escuela']], ['clave' => 'b', 'texto' => ['es' => 'Delante de la tienda']], ['clave' => 'c', 'texto' => ['es' => 'Lejos']]],
 'correcta' => 'a'],
['tipo' => 'dictado', 'descriptor' => 'A1.CO.1', 'lengua' => 'zh', 'seq' => 2,
 'clip' => 'zh/u9/tingxie-1', 'transcripcion' => ['zh' => 'Qǐng zài shuō yí biàn.'],
 'consigna' => ['es' => 'Escucha y escribe la frase en pinyin, con los tonos.'],
 'aceptadas' => ['qǐng zài shuō yí biàn', 'qing3 zai4 shuo1 yi2 bian4', '请再说一遍']],
```

### 3 · El interlocutor

El guion de la U1 de chino para `dialogos-lenguas.php` (el mismo 第一天) se
escribe cuando estén `zh/u1/escena` y los clips por réplica; hasta entonces el
interlocutor de chino no se declara, porque un interlocutor de chino sin voz
enseña a leer sin poder hablar — exactamente lo que el curso quiere evitar.

---

## Opción rápida: voz sintética

Si no hay un hablante nativo a mano, una voz sintética de chino estándar sirve
para los clips de **vocabulario y dictado** (palabras sueltas y frases cortas,
donde lo que importa es el tono), y se sustituye por la voz humana cuando la haya.
Para las **escenas** a dos voces, mejor esperar a personas: la entonación de la
frase entera (U8) no la hace bien una máquina. Esto gasta créditos de una
herramienta de pago: se decide antes, no se hace por defecto.
