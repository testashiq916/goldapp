from django.db import models


class Alloys(models.Model):
    name = models.CharField(primary_key=True, max_length=10)
    perc = models.DecimalField(max_digits=5, decimal_places=2)

    class Meta:
        managed = False
        db_table = 'alloys'


class Area(models.Model):
    code = models.CharField(primary_key=True, max_length=10)
    name = models.CharField(max_length=30)

    class Meta:
        managed = False
        db_table = 'area'


class Codehelp(models.Model):
    code = models.CharField(primary_key=True, max_length=10)

    class Meta:
        managed = False
        db_table = 'codehelp'


class Counter(models.Model):
    code = models.CharField(primary_key=True, max_length=5)
    name = models.CharField(max_length=15)
    startbillno = models.DecimalField(max_digits=10, decimal_places=0, blank=True, null=True)

    class Meta:
        managed = False
        db_table = 'counter'


class DenomMaster(models.Model):
    code = models.CharField(primary_key=True, max_length=10)
    name = models.CharField(max_length=20)
    cvalue = models.DecimalField(max_digits=8, decimal_places=2, blank=True, null=True)

    class Meta:
        managed = False
        db_table = 'denom_master'


class DenomTrans(models.Model):
    pk = models.CompositePrimaryKey('pk', 'tdate')
    pk = models.CompositePrimaryKey('tdate', 'denom_code')
    tdate = models.DateField()
    denom_code = models.CharField(max_length=10)
    nos = models.IntegerField()
    totalvalue = models.DecimalField(max_digits=13, decimal_places=2)

    class Meta:
        managed = False
        db_table = 'denom_trans'


class Fashion(models.Model):
    itemcode = models.CharField(max_length=10, blank=True, null=True)
    slno = models.AutoField(primary_key=True)
    pfile = models.CharField(max_length=40, blank=True, null=True)

    class Meta:
        managed = False
        db_table = 'fashion'


class Itemgrp(models.Model):
    pk = models.CompositePrimaryKey('code', 'name')
    code = models.CharField(max_length=10)
    name = models.CharField(max_length=30)
    mname = models.CharField(max_length=30)
    itype = models.CharField(max_length=1)
    orn = models.CharField(max_length=1)
    pos = models.IntegerField(blank=True, null=True)
    showinstkrep = models.CharField(max_length=1, blank=True, null=True)

    class Meta:
        managed = False
        db_table = 'itemgrp'


class Items(models.Model):
    code = models.CharField(primary_key=True, max_length=8)
    name = models.CharField(max_length=20, blank=True, null=True)
    regionalname = models.CharField(max_length=20, blank=True, null=True)
    itype = models.CharField(max_length=1, blank=True, null=True)
    reserve = models.CharField(max_length=1, blank=True, null=True)
    ornament = models.CharField(max_length=1, blank=True, null=True)
    display = models.CharField(max_length=1, blank=True, null=True)
    wastage = models.DecimalField(max_digits=6, decimal_places=3, blank=True, null=True)
    mcharge = models.DecimalField(max_digits=6, decimal_places=2, blank=True, null=True)
    opqty = models.IntegerField(blank=True, null=True)
    opweight = models.DecimalField(max_digits=10, decimal_places=3, blank=True, null=True)
    opstonewgt = models.DecimalField(max_digits=8, decimal_places=3, blank=True, null=True)
    opcost = models.DecimalField(max_digits=8, decimal_places=2, blank=True, null=True)
    opqtyb = models.IntegerField(blank=True, null=True)
    opweightb = models.DecimalField(max_digits=10, decimal_places=3, blank=True, null=True)
    opstonewgtb = models.DecimalField(max_digits=8, decimal_places=3, blank=True, null=True)
    qty = models.IntegerField(blank=True, null=True)
    weight = models.DecimalField(max_digits=10, decimal_places=3, blank=True, null=True)
    stonewgt = models.DecimalField(max_digits=8, decimal_places=3, blank=True, null=True)
    cost = models.DecimalField(max_digits=8, decimal_places=2, blank=True, null=True)
    qtyb = models.IntegerField(blank=True, null=True)
    weightb = models.DecimalField(max_digits=10, decimal_places=3, blank=True, null=True)
    stonewgtb = models.DecimalField(max_digits=8, decimal_places=3, blank=True, null=True)
    rollower = models.DecimalField(max_digits=10, decimal_places=3, blank=True, null=True)
    rolupper = models.DecimalField(max_digits=10, decimal_places=3, blank=True, null=True)
    vaperc = models.DecimalField(max_digits=5, decimal_places=2, blank=True, null=True)
    grpcode = models.CharField(max_length=10, blank=True, null=True)
    patcfrgt = models.DecimalField(max_digits=8, decimal_places=2, blank=True, null=True)
    psecfrgt = models.DecimalField(max_digits=8, decimal_places=2, blank=True, null=True)
    defqty = models.IntegerField(blank=True, null=True)
    stkinnos = models.CharField(max_length=1, blank=True, null=True)
    code2 = models.CharField(max_length=10, blank=True, null=True)
    touch = models.DecimalField(max_digits=6, decimal_places=2, blank=True, null=True)
    disabled = models.SmallIntegerField(blank=True, null=True)
    qtype = models.CharField(max_length=10, blank=True, null=True)
    vatcode = models.CharField(max_length=10, blank=True, null=True)
    shedule = models.CharField(max_length=10, blank=True, null=True)
    defstktype = models.CharField(max_length=5, blank=True, null=True)
    defquality = models.CharField(max_length=10, blank=True, null=True)
    defsmith = models.CharField(max_length=10, blank=True, null=True)
    footer_e = models.CharField(max_length=40, blank=True, null=True)
    footer_m = models.CharField(max_length=40, blank=True, null=True)
    stonemarg = models.DecimalField(max_digits=5, decimal_places=2, blank=True, null=True)
    opstoneamt = models.DecimalField(max_digits=10, decimal_places=2, blank=True, null=True)
    opstoneamtb = models.DecimalField(max_digits=10, decimal_places=2, blank=True, null=True)
    showinstkrep = models.CharField(max_length=1, blank=True, null=True)
    vaperqty = models.DecimalField(max_digits=10, decimal_places=2, blank=True, null=True)
    rate = models.DecimalField(max_digits=9, decimal_places=2, blank=True, null=True)
    smithmc = models.DecimalField(max_digits=9, decimal_places=2, blank=True, null=True)
    jewlmc = models.DecimalField(max_digits=9, decimal_places=2, blank=True, null=True)
    stktouch = models.DecimalField(max_digits=5, decimal_places=2, blank=True, null=True)
    dmdplt = models.CharField(max_length=1, blank=True, null=True)
    opdmdwgt = models.DecimalField(max_digits=9, decimal_places=3, blank=True, null=True)
    crate = models.DecimalField(max_digits=9, decimal_places=2, blank=True, null=True)
    billtype = models.CharField(max_length=10, blank=True, null=True)
    nodisc = models.CharField(max_length=1, blank=True, null=True)
    stickerwgt = models.DecimalField(max_digits=5, decimal_places=3, blank=True, null=True)
    stonemust = models.CharField(max_length=1, blank=True, null=True)
    cessinternal = models.CharField(max_length=1, blank=True, null=True)
    taxable = models.CharField(max_length=1, blank=True, null=True)
    printvaamt = models.CharField(max_length=1, blank=True, null=True)
    subgrpcode = models.CharField(max_length=10, blank=True, null=True)
    jewltouch = models.DecimalField(max_digits=6, decimal_places=2, blank=True, null=True)
    wsrate = models.DecimalField(max_digits=9, decimal_places=2, blank=True, null=True)
    rollowerqty = models.IntegerField(blank=True, null=True)
    rolupperqty = models.IntegerField(blank=True, null=True)
    clstock = models.DecimalField(max_digits=10, decimal_places=3, blank=True, null=True)
    clqty = models.DecimalField(max_digits=10, decimal_places=0, blank=True, null=True)
    clstone = models.DecimalField(max_digits=10, decimal_places=3, blank=True, null=True)
    taxinternal = models.CharField(max_length=1, blank=True, null=True)
    bccompulsory = models.CharField(max_length=1, blank=True, null=True)
    prate = models.DecimalField(max_digits=10, decimal_places=2, blank=True, null=True)
    saccode = models.CharField(max_length=10, blank=True, null=True)
    minvap = models.DecimalField(max_digits=10, decimal_places=0, blank=True, null=True)
    vaoffer = models.CharField(max_length=1, blank=True, null=True)

    class Meta:
        managed = False
        db_table = 'items'


class Itemsothers(models.Model):
    code = models.CharField(primary_key=True, max_length=10)
    name = models.CharField(max_length=30)
    opstock = models.IntegerField(blank=True, null=True)
    stock = models.IntegerField(blank=True, null=True)
    srate = models.DecimalField(max_digits=10, decimal_places=2, blank=True, null=True)
    prate = models.DecimalField(max_digits=10, decimal_places=2, blank=True, null=True)
    cost = models.DecimalField(max_digits=10, decimal_places=2, blank=True, null=True)
    opcost = models.DecimalField(max_digits=10, decimal_places=2, blank=True, null=True)
    grp = models.CharField(max_length=10, blank=True, null=True)
    keepstk = models.SmallIntegerField(blank=True, null=True)

    class Meta:
        managed = False
        db_table = 'itemsothers'


class Itemsqtype(models.Model):
    code = models.CharField(primary_key=True, max_length=10)
    touch = models.DecimalField(max_digits=8, decimal_places=3, blank=True, null=True)
    rate = models.DecimalField(max_digits=10, decimal_places=3, blank=True, null=True)
    convrate = models.DecimalField(max_digits=10, decimal_places=4, blank=True, null=True)

    class Meta:
        managed = False
        db_table = 'itemsqtype'


class Itemsubgrp(models.Model):
    code = models.CharField(primary_key=True, max_length=10)
    name = models.CharField(max_length=30)

    class Meta:
        managed = False
        db_table = 'itemsubgrp'


class Kuritype(models.Model):
    code = models.CharField(primary_key=True, max_length=10)
    name = models.CharField(max_length=40, blank=True, null=True)
    instnos = models.SmallIntegerField(blank=True, null=True)
    instamt = models.DecimalField(max_digits=8, decimal_places=2, blank=True, null=True)
    totamt = models.DecimalField(max_digits=10, decimal_places=2, blank=True, null=True)
    bonus = models.DecimalField(max_digits=8, decimal_places=2, blank=True, null=True)
    colntype = models.CharField(max_length=2, blank=True, null=True)
    prefix = models.CharField(max_length=6, blank=True, null=True)
    lastno = models.DecimalField(max_digits=10, decimal_places=0, blank=True, null=True)
    collnlimit = models.DecimalField(max_digits=10, decimal_places=2, blank=True, null=True)
    collnmin = models.DecimalField(max_digits=10, decimal_places=2, blank=True, null=True)
    comnrate = models.DecimalField(max_digits=8, decimal_places=2, blank=True, null=True)

    class Meta:
        managed = False
        db_table = 'kuritype'


class Mctable(models.Model):
    code = models.CharField(primary_key=True, max_length=10)
    weight1 = models.DecimalField(max_digits=9, decimal_places=3, blank=True, null=True)
    weight2 = models.DecimalField(max_digits=9, decimal_places=3, blank=True, null=True)
    mc = models.DecimalField(max_digits=9, decimal_places=2, blank=True, null=True)
    mcpergm = models.DecimalField(max_digits=9, decimal_places=2, blank=True, null=True)
    mcperqty = models.DecimalField(max_digits=9, decimal_places=2, blank=True, null=True)
    vaperc = models.DecimalField(max_digits=5, decimal_places=2, blank=True, null=True)
    iqtype = models.CharField(max_length=10, blank=True, null=True)

    class Meta:
        managed = False
        db_table = 'mctable'


class Modelm(models.Model):
    slno = models.DecimalField(primary_key=True, max_digits=10, decimal_places=0)
    tdate = models.DateField(blank=True, null=True)
    pcode = models.CharField(max_length=10, blank=True, null=True)
    pname = models.CharField(max_length=30, blank=True, null=True)
    icode = models.CharField(max_length=10, blank=True, null=True)
    bcode = models.DecimalField(max_digits=10, decimal_places=0, blank=True, null=True)
    qty = models.SmallIntegerField(blank=True, null=True)
    weight = models.DecimalField(max_digits=9, decimal_places=3, blank=True, null=True)
    stwgt = models.DecimalField(max_digits=8, decimal_places=3, blank=True, null=True)
    ir = models.CharField(max_length=1, blank=True, null=True)
    pend = models.CharField(max_length=1, blank=True, null=True)
    islno = models.DecimalField(max_digits=10, decimal_places=0, blank=True, null=True)
    control = models.SmallIntegerField(blank=True, null=True)
    smcode = models.CharField(max_length=10, blank=True, null=True)
    stktype = models.CharField(max_length=10, blank=True, null=True)
    note = models.CharField(max_length=30, blank=True, null=True)
    gr = models.CharField(max_length=1, blank=True, null=True)

    class Meta:
        managed = False
        db_table = 'modelm'


class Models(models.Model):
    pk = models.CompositePrimaryKey('pk', 'mtype')
    pk = models.CompositePrimaryKey('mtype', 'name')
    mtype = models.CharField(max_length=1)
    name = models.CharField(max_length=15)

    class Meta:
        managed = False
        db_table = 'models'


class Nationality(models.Model):
    name = models.CharField(primary_key=True, max_length=20)

    class Meta:
        managed = False
        db_table = 'nationality'


class Pmctable(models.Model):
    pk = models.CompositePrimaryKey('pk', 'pcode')
    pk = models.CompositePrimaryKey('pcode', 'icode', 'model', 'submodel')
    pcode = models.CharField(max_length=10)
    icode = models.CharField(max_length=10)
    model = models.CharField(max_length=15)
    submodel = models.CharField(max_length=15)
    wastage = models.DecimalField(max_digits=5, decimal_places=2, blank=True, null=True)
    mc = models.DecimalField(max_digits=5, decimal_places=2, blank=True, null=True)
    mcperc = models.DecimalField(max_digits=5, decimal_places=2, blank=True, null=True)
    mcperqty = models.DecimalField(max_digits=5, decimal_places=2, blank=True, null=True)
    touch = models.DecimalField(max_digits=5, decimal_places=2, blank=True, null=True)
    formula = models.CharField(max_length=15, blank=True, null=True)

    class Meta:
        managed = False
        db_table = 'pmctable'


class Rotable(models.Model):
    pk = models.CompositePrimaryKey('pk', 'code')
    pk = models.CompositePrimaryKey('code', 'model', 'size', 'weight1', 'weight2')
    code = models.CharField(max_length=10)
    model = models.CharField(max_length=20)
    size = models.CharField(max_length=20)
    weight1 = models.DecimalField(max_digits=10, decimal_places=3)
    weight2 = models.DecimalField(max_digits=10, decimal_places=3)
    minqty = models.IntegerField(blank=True, null=True)
    maxqty = models.IntegerField(blank=True, null=True)

    class Meta:
        managed = False
        db_table = 'rotable'


class Route(models.Model):
    code = models.CharField(primary_key=True, max_length=10)
    name = models.CharField(max_length=40, blank=True, null=True)

    class Meta:
        managed = False
        db_table = 'route'


class Salestype(models.Model):
    code = models.CharField(primary_key=True, max_length=10)
    name = models.CharField(max_length=20, blank=True, null=True)
    prefix = models.CharField(max_length=5, blank=True, null=True)
    startno = models.IntegerField(blank=True, null=True)
    formno = models.CharField(max_length=5, blank=True, null=True)
    taxperc = models.DecimalField(max_digits=6, decimal_places=2, blank=True, null=True)
    pprefix = models.CharField(max_length=5, blank=True, null=True)
    pstartno = models.IntegerField(blank=True, null=True)
    srprefix = models.CharField(max_length=5, blank=True, null=True)
    srstartno = models.IntegerField(blank=True, null=True)
    prprefix = models.CharField(max_length=5, blank=True, null=True)
    prstartno = models.IntegerField(blank=True, null=True)

    class Meta:
        managed = False
        db_table = 'salestype'


class Saletype(models.Model):
    code = models.CharField(primary_key=True, max_length=2)
    name = models.CharField(max_length=30)
    prefix = models.CharField(max_length=3, blank=True, null=True)

    class Meta:
        managed = False
        db_table = 'saletype'


class Sman(models.Model):
    code = models.CharField(primary_key=True, max_length=8)
    name = models.CharField(max_length=30, blank=True, null=True)
    totsale = models.DecimalField(max_digits=10, decimal_places=3, blank=True, null=True)
    cat = models.CharField(max_length=5, blank=True, null=True)
    comn = models.DecimalField(max_digits=10, decimal_places=2, blank=True, null=True)
    accode = models.CharField(max_length=10, blank=True, null=True)
    active = models.CharField(max_length=1, blank=True, null=True)

    class Meta:
        managed = False
        db_table = 'sman'


class State(models.Model):
    code = models.CharField(primary_key=True, max_length=10)
    name = models.CharField(max_length=20)

    class Meta:
        managed = False
        db_table = 'state'


class Stktype(models.Model):
    code = models.CharField(primary_key=True, max_length=5)
    name = models.CharField(max_length=20, blank=True, null=True)
    def_field = models.SmallIntegerField(db_column='def', blank=True, null=True)  # Field renamed because it was a Python reserved word.
    compare = models.SmallIntegerField(blank=True, null=True)

    class Meta:
        managed = False
        db_table = 'stktype'


class Wastage(models.Model):
    cno = models.IntegerField()
    llimit = models.DecimalField(primary_key=True, max_digits=7, decimal_places=3)
    ulimit = models.DecimalField(max_digits=7, decimal_places=3)
    wastage = models.DecimalField(max_digits=7, decimal_places=3, blank=True, null=True)

    class Meta:
        managed = False
        db_table = 'wastage'
