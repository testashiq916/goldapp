from django.db import models


class Barcode(models.Model):
    bcode = models.DecimalField(primary_key=True, max_digits=10, decimal_places=0)
    icode = models.CharField(max_length=10)
    qty = models.IntegerField(blank=True, null=True)
    weight = models.DecimalField(max_digits=10, decimal_places=3, blank=True, null=True)
    stweight = models.DecimalField(max_digits=10, decimal_places=3, blank=True, null=True)
    stprice = models.DecimalField(max_digits=8, decimal_places=2, blank=True, null=True)
    wastage = models.DecimalField(max_digits=8, decimal_places=3, blank=True, null=True)
    mc = models.DecimalField(max_digits=8, decimal_places=2, blank=True, null=True)
    dmdwgt = models.DecimalField(max_digits=9, decimal_places=3, blank=True, null=True)
    dmdunit = models.CharField(max_length=15, blank=True, null=True)
    dmdamt = models.DecimalField(max_digits=10, decimal_places=2, blank=True, null=True)
    tdate = models.DateField(blank=True, null=True)
    control = models.SmallIntegerField(blank=True, null=True)
    smcode = models.CharField(max_length=10, blank=True, null=True)
    part = models.CharField(max_length=30, blank=True, null=True)
    mcrate = models.DecimalField(max_digits=8, decimal_places=2, blank=True, null=True)
    rslno = models.DecimalField(max_digits=10, decimal_places=0, blank=True, null=True)
    islno = models.DecimalField(max_digits=10, decimal_places=0, blank=True, null=True)
    stk = models.CharField(max_length=1, blank=True, null=True)
    dmdnos = models.IntegerField(blank=True, null=True)
    rate = models.DecimalField(max_digits=8, decimal_places=2, blank=True, null=True)
    taken = models.SmallIntegerField(blank=True, null=True)
    counter = models.CharField(max_length=5, blank=True, null=True)
    qtype = models.CharField(max_length=6, blank=True, null=True)
    qunit = models.CharField(max_length=6, blank=True, null=True)
    smithmcrate = models.DecimalField(max_digits=8, decimal_places=2, blank=True, null=True)
    vap = models.DecimalField(max_digits=5, decimal_places=2, blank=True, null=True)
    sdate = models.DateField(blank=True, null=True)
    weight2 = models.DecimalField(max_digits=10, decimal_places=3, blank=True, null=True)
    docno = models.CharField(max_length=10, blank=True, null=True)
    cost = models.DecimalField(max_digits=10, decimal_places=2, blank=True, null=True)
    tamt = models.DecimalField(max_digits=12, decimal_places=2, blank=True, null=True)
    goldct = models.DecimalField(max_digits=5, decimal_places=2, blank=True, null=True)
    stkinnos = models.CharField(max_length=1, blank=True, null=True)
    costamt = models.DecimalField(max_digits=10, decimal_places=2, blank=True, null=True)
    note = models.CharField(max_length=50, blank=True, null=True)
    smithcode = models.CharField(max_length=10, blank=True, null=True)
    serialno = models.CharField(max_length=30, blank=True, null=True)
    nodisc = models.CharField(max_length=1, blank=True, null=True)
    subgrp = models.CharField(max_length=10, blank=True, null=True)
    costperc = models.DecimalField(max_digits=5, decimal_places=2, blank=True, null=True)
    transtouch = models.DecimalField(max_digits=6, decimal_places=3, blank=True, null=True)
    stktouch = models.DecimalField(max_digits=6, decimal_places=3, blank=True, null=True)
    costmc = models.DecimalField(max_digits=8, decimal_places=2, blank=True, null=True)
    grate = models.DecimalField(max_digits=8, decimal_places=2, blank=True, null=True)
    coststone = models.DecimalField(max_digits=9, decimal_places=2, blank=True, null=True)
    pqty = models.IntegerField(blank=True, null=True)
    pwgt = models.DecimalField(max_digits=9, decimal_places=3, blank=True, null=True)
    pstwgt = models.DecimalField(max_digits=9, decimal_places=3, blank=True, null=True)
    sizemodel = models.CharField(max_length=10, blank=True, null=True)
    model = models.CharField(max_length=15, blank=True, null=True)
    minvap = models.DecimalField(max_digits=5, decimal_places=2, blank=True, null=True)
    status = models.CharField(max_length=1, blank=True, null=True)
    huid = models.CharField(max_length=20, blank=True, null=True)
    mccostperc = models.DecimalField(max_digits=5, decimal_places=2, blank=True, null=True)

    class Meta:
        managed = False
        db_table = 'barcode'


class BarcodeDmddet(models.Model):
    pk = models.CompositePrimaryKey('slno', 'bcode')
    slno = models.DecimalField(max_digits=10, decimal_places=0)
    bcode = models.DecimalField(max_digits=10, decimal_places=0)
    sno = models.SmallIntegerField(blank=True, null=True)
    sttype = models.CharField(max_length=10, blank=True, null=True)
    stsize = models.CharField(max_length=10, blank=True, null=True)
    stcut = models.CharField(max_length=10, blank=True, null=True)
    stsettype = models.CharField(max_length=10, blank=True, null=True)
    pcs = models.SmallIntegerField(blank=True, null=True)
    carats = models.DecimalField(max_digits=6, decimal_places=3, blank=True, null=True)
    rate = models.DecimalField(max_digits=12, decimal_places=2, blank=True, null=True)
    amount = models.DecimalField(max_digits=12, decimal_places=2, blank=True, null=True)
    mperc = models.DecimalField(max_digits=8, decimal_places=2, blank=True, null=True)
    stcolor = models.CharField(max_length=10, blank=True, null=True)
    stcode = models.CharField(max_length=10, blank=True, null=True)
    wgt = models.DecimalField(max_digits=8, decimal_places=3, blank=True, null=True)
    prate = models.DecimalField(max_digits=12, decimal_places=2, blank=True, null=True)
    pamt = models.DecimalField(max_digits=12, decimal_places=2, blank=True, null=True)

    class Meta:
        managed = False
        db_table = 'barcode_dmddet'


class Barcodedmd(models.Model):
    bcode = models.DecimalField(primary_key=True, max_digits=10, decimal_places=0)
    dmdwgt = models.DecimalField(max_digits=9, decimal_places=3, blank=True, null=True)
    dmdnos = models.IntegerField(blank=True, null=True)
    dmdunit = models.CharField(max_length=10, blank=True, null=True)
    dmdamt = models.DecimalField(max_digits=9, decimal_places=2, blank=True, null=True)
    brand = models.CharField(max_length=10, blank=True, null=True)
    purity = models.CharField(max_length=10, blank=True, null=True)
    centrate = models.DecimalField(max_digits=9, decimal_places=2, blank=True, null=True)
    salesamt = models.DecimalField(max_digits=11, decimal_places=2, blank=True, null=True)

    class Meta:
        managed = False
        db_table = 'barcodedmd'


class Barcodedoc(models.Model):
    docno = models.CharField(primary_key=True, max_length=10)
    tdate = models.DateField(blank=True, null=True)
    smith = models.CharField(max_length=10, blank=True, null=True)
    totwgt = models.DecimalField(max_digits=10, decimal_places=3, blank=True, null=True)
    totnos = models.DecimalField(max_digits=10, decimal_places=0, blank=True, null=True)

    class Meta:
        managed = False
        db_table = 'barcodedoc'


class Itemadj(models.Model):
    pk = models.CompositePrimaryKey('tdate', 'fromcode')
    tdate = models.DateField()
    ttime = models.TimeField(blank=True, null=True)
    fromcode = models.CharField(max_length=8)
    fromqty = models.IntegerField(blank=True, null=True)
    fromwgt = models.DecimalField(max_digits=8, decimal_places=3, blank=True, null=True)
    fromcost = models.DecimalField(max_digits=7, decimal_places=2, blank=True, null=True)
    tocode = models.CharField(max_length=8)
    toqty = models.IntegerField(blank=True, null=True)
    towgt = models.DecimalField(max_digits=8, decimal_places=3, blank=True, null=True)
    tocost = models.DecimalField(max_digits=7, decimal_places=2, blank=True, null=True)
    particular = models.CharField(max_length=30, blank=True, null=True)
    control = models.SmallIntegerField(blank=True, null=True)
    slno = models.DecimalField(max_digits=10, decimal_places=0, blank=True, null=True)
    smcode = models.CharField(max_length=10, blank=True, null=True)
    sno = models.SmallIntegerField(blank=True, null=True)
    fromstwgt = models.DecimalField(max_digits=8, decimal_places=3, blank=True, null=True)
    tostwgt = models.DecimalField(max_digits=8, decimal_places=3, blank=True, null=True)
    al = models.CharField(max_length=1, blank=True, null=True)
    mark = models.CharField(max_length=1, blank=True, null=True)
    fromstktype = models.CharField(max_length=5, blank=True, null=True)
    tostktype = models.CharField(max_length=5, blank=True, null=True)
    ichange = models.SmallIntegerField(blank=True, null=True)
    fromstamt = models.DecimalField(max_digits=9, decimal_places=2, blank=True, null=True)
    tostamt = models.DecimalField(max_digits=9, decimal_places=2, blank=True, null=True)
    ic = models.CharField(max_length=5, blank=True, null=True)
    fromstktouch = models.DecimalField(max_digits=5, decimal_places=2, blank=True, null=True)
    tostktouch = models.DecimalField(max_digits=5, decimal_places=2, blank=True, null=True)
    bcode = models.DecimalField(max_digits=10, decimal_places=0, blank=True, null=True)
    tobcode = models.DecimalField(max_digits=10, decimal_places=0, blank=True, null=True)
    tbcode = models.CharField(max_length=15, blank=True, null=True)
    refno = models.CharField(max_length=10, blank=True, null=True)

    class Meta:
        managed = False
        db_table = 'itemadj'


class Itemadjverify(models.Model):
    sno = models.AutoField(primary_key=True)
    code = models.CharField(max_length=10)
    tdate = models.DateField(blank=True, null=True)
    addqty = models.IntegerField(blank=True, null=True)
    addwgt = models.DecimalField(max_digits=10, decimal_places=3, blank=True, null=True)
    addnetwgt = models.DecimalField(max_digits=10, decimal_places=3, blank=True, null=True)
    lessqty = models.IntegerField(blank=True, null=True)
    lesswgt = models.DecimalField(max_digits=10, decimal_places=3, blank=True, null=True)
    lessnetwgt = models.DecimalField(max_digits=10, decimal_places=3, blank=True, null=True)
    control = models.SmallIntegerField(blank=True, null=True)
    stktype = models.CharField(max_length=5, blank=True, null=True)

    class Meta:
        managed = False
        db_table = 'itemadjverify'


class Itemsstk(models.Model):
    pk = models.CompositePrimaryKey('pk', 'code')
    pk = models.CompositePrimaryKey('code', 'stktype')
    code = models.CharField(max_length=10)
    stktype = models.CharField(max_length=5)
    opqty = models.IntegerField(blank=True, null=True)
    opweight = models.DecimalField(max_digits=10, decimal_places=3, blank=True, null=True)
    opstonewgt = models.DecimalField(max_digits=8, decimal_places=3, blank=True, null=True)
    qty = models.IntegerField(blank=True, null=True)
    weight = models.DecimalField(max_digits=10, decimal_places=3, blank=True, null=True)
    stonewgt = models.DecimalField(max_digits=8, decimal_places=3, blank=True, null=True)
    opqtyb = models.IntegerField(blank=True, null=True)
    opweightb = models.DecimalField(max_digits=10, decimal_places=3, blank=True, null=True)
    opstonewgtb = models.DecimalField(max_digits=8, decimal_places=3, blank=True, null=True)
    qtyb = models.IntegerField(blank=True, null=True)
    weightb = models.DecimalField(max_digits=10, decimal_places=3, blank=True, null=True)
    stonewgtb = models.DecimalField(max_digits=8, decimal_places=3, blank=True, null=True)
    opstoneamt = models.DecimalField(max_digits=10, decimal_places=2, blank=True, null=True)
    opstoneamtb = models.DecimalField(max_digits=10, decimal_places=2, blank=True, null=True)
    opdmdwgt = models.DecimalField(max_digits=9, decimal_places=3, blank=True, null=True)

    class Meta:
        managed = False
        db_table = 'itemsstk'


class Itemstmp(models.Model):
    pk = models.CompositePrimaryKey('pk', 'code')
    pk = models.CompositePrimaryKey('code', 'name')
    code = models.CharField(max_length=10)
    name = models.CharField(max_length=30)
    mname = models.CharField(max_length=30)
    iqtype = models.CharField(max_length=10, blank=True, null=True)

    class Meta:
        managed = False
        db_table = 'itemstmp'


class Spdmddet(models.Model):
    slno = models.DecimalField(primary_key=True, max_digits=10, decimal_places=0)
    sno = models.SmallIntegerField(blank=True, null=True)
    dmdwgt = models.DecimalField(max_digits=8, decimal_places=3, blank=True, null=True)
    dmdunit = models.CharField(max_length=10, blank=True, null=True)
    dmdnos = models.IntegerField(blank=True, null=True)
    brand = models.CharField(max_length=10, blank=True, null=True)
    purity = models.CharField(max_length=10, blank=True, null=True)
    centrate = models.DecimalField(max_digits=9, decimal_places=2, blank=True, null=True)
    dmdamt = models.DecimalField(max_digits=9, decimal_places=2, blank=True, null=True)

    class Meta:
        managed = False
        db_table = 'spdmddet'


class Stkandprofit(models.Model):
    slno = models.DecimalField(primary_key=True, max_digits=10, decimal_places=0)
    tdate = models.DateField(blank=True, null=True)
    stkvalue = models.DecimalField(max_digits=9, decimal_places=2, blank=True, null=True)
    profit = models.DecimalField(max_digits=9, decimal_places=2, blank=True, null=True)
    note = models.CharField(max_length=10, blank=True, null=True)
    control = models.SmallIntegerField(blank=True, null=True)
    docno = models.CharField(max_length=10, blank=True, null=True)

    class Meta:
        managed = False
        db_table = 'stkandprofit'
