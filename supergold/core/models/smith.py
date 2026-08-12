from django.db import models


class Refineryd(models.Model):
    pk = models.CompositePrimaryKey('slno', 'code')
    slno = models.DecimalField(max_digits=10, decimal_places=0)
    code = models.CharField(max_length=8)
    issuedwgt = models.DecimalField(max_digits=8, decimal_places=3, blank=True, null=True)
    issuedqty = models.IntegerField(blank=True, null=True)
    rcvdwgt = models.DecimalField(max_digits=8, decimal_places=3, blank=True, null=True)
    rcvdqty = models.IntegerField(blank=True, null=True)
    bottlestk = models.DecimalField(max_digits=7, decimal_places=3, blank=True, null=True)
    testpcs = models.DecimalField(max_digits=7, decimal_places=3, blank=True, null=True)
    oissuedwgt = models.DecimalField(max_digits=8, decimal_places=3, blank=True, null=True)
    status = models.SmallIntegerField(blank=True, null=True)
    cost = models.DecimalField(max_digits=10, decimal_places=2, blank=True, null=True)
    rate = models.DecimalField(max_digits=8, decimal_places=2, blank=True, null=True)
    rcvdwgtamt = models.DecimalField(max_digits=10, decimal_places=2, blank=True, null=True)
    issuedwgtamt = models.DecimalField(max_digits=10, decimal_places=2, blank=True, null=True)
    sno = models.SmallIntegerField(blank=True, null=True)
    mudless = models.DecimalField(max_digits=6, decimal_places=3, blank=True, null=True)
    ao = models.CharField(max_length=1, blank=True, null=True)
    coper = models.DecimalField(max_digits=8, decimal_places=3, blank=True, null=True)
    mark = models.CharField(max_length=1, blank=True, null=True)
    issuedstwgt = models.DecimalField(max_digits=8, decimal_places=3, blank=True, null=True)
    stktype = models.CharField(max_length=5, blank=True, null=True)
    stktouch = models.DecimalField(max_digits=5, decimal_places=2, blank=True, null=True)
    touch = models.DecimalField(max_digits=5, decimal_places=2, blank=True, null=True)
    batch = models.CharField(max_length=10, blank=True, null=True)
    rcvdtouch = models.DecimalField(max_digits=5, decimal_places=2, blank=True, null=True)

    class Meta:
        managed = False
        db_table = 'refineryd'


class Refinerym(models.Model):
    slno = models.DecimalField(primary_key=True, max_digits=10, decimal_places=0)
    docno = models.CharField(max_length=10)
    tdate = models.DateField(blank=True, null=True)
    ttime = models.TimeField(blank=True, null=True)
    refcode = models.CharField(max_length=8, blank=True, null=True)
    tbottlestk = models.DecimalField(max_digits=7, decimal_places=3, blank=True, null=True)
    ttestpcs = models.DecimalField(max_digits=7, decimal_places=3, blank=True, null=True)
    charge = models.DecimalField(max_digits=8, decimal_places=2, blank=True, null=True)
    paidamt = models.DecimalField(max_digits=8, decimal_places=2, blank=True, null=True)
    toldissuedwgt = models.DecimalField(max_digits=8, decimal_places=3, blank=True, null=True)
    testperc = models.DecimalField(max_digits=5, decimal_places=2, blank=True, null=True)
    status = models.SmallIntegerField(blank=True, null=True)
    control = models.SmallIntegerField(blank=True, null=True)
    smcode = models.CharField(max_length=10, blank=True, null=True)
    ic = models.CharField(max_length=5, blank=True, null=True)
    expwgt = models.DecimalField(max_digits=8, decimal_places=3, blank=True, null=True)
    note = models.CharField(max_length=30, blank=True, null=True)

    class Meta:
        managed = False
        db_table = 'refinerym'


class Ruffwrk(models.Model):
    slno = models.DecimalField(primary_key=True, max_digits=10, decimal_places=0)
    party = models.CharField(max_length=30, blank=True, null=True)
    tdate = models.DateField(blank=True, null=True)
    item = models.CharField(max_length=30, blank=True, null=True)
    amount = models.CharField(max_length=30, blank=True, null=True)
    part = models.CharField(max_length=30, blank=True, null=True)
    pend = models.SmallIntegerField(blank=True, null=True)
    number = models.SmallIntegerField(blank=True, null=True)
    control = models.SmallIntegerField(blank=True, null=True)
    weight = models.CharField(max_length=20, blank=True, null=True)
    qty = models.CharField(max_length=20, blank=True, null=True)
    inexp = models.CharField(max_length=3, blank=True, null=True)
    sman = models.CharField(max_length=30, blank=True, null=True)
    person = models.CharField(max_length=30, blank=True, null=True)

    class Meta:
        managed = False
        db_table = 'ruffwrk'


class Smithd(models.Model):
    pk = models.CompositePrimaryKey('slno', 'code')
    slno = models.DecimalField(max_digits=10, decimal_places=0)
    code = models.CharField(max_length=8)
    qty = models.IntegerField(blank=True, null=True)
    weight = models.DecimalField(max_digits=12, decimal_places=3, blank=True, null=True)
    stonewgt = models.DecimalField(max_digits=8, decimal_places=3, blank=True, null=True)
    wastage = models.DecimalField(max_digits=8, decimal_places=3, blank=True, null=True)
    givrec = models.CharField(max_length=1, blank=True, null=True)
    mcharge = models.DecimalField(max_digits=10, decimal_places=2, blank=True, null=True)
    cost = models.DecimalField(max_digits=9, decimal_places=2, blank=True, null=True)
    stoneprice = models.DecimalField(max_digits=8, decimal_places=2, blank=True, null=True)
    wgtamt = models.DecimalField(max_digits=10, decimal_places=2, blank=True, null=True)
    touch = models.DecimalField(max_digits=6, decimal_places=3, blank=True, null=True)
    touchwgt = models.DecimalField(max_digits=12, decimal_places=3, blank=True, null=True)
    name = models.CharField(max_length=30, blank=True, null=True)
    sno = models.SmallIntegerField(blank=True, null=True)
    ordno = models.CharField(max_length=30, blank=True, null=True)
    mark = models.CharField(max_length=1, blank=True, null=True)
    orditem = models.CharField(max_length=10, blank=True, null=True)
    touchnote = models.CharField(max_length=10, blank=True, null=True)
    netwgt = models.DecimalField(max_digits=12, decimal_places=3, blank=True, null=True)
    stktype = models.CharField(max_length=5, blank=True, null=True)
    bcode = models.DecimalField(max_digits=10, decimal_places=0, blank=True, null=True)
    smithmc = models.DecimalField(max_digits=9, decimal_places=2, blank=True, null=True)
    stktouch = models.DecimalField(max_digits=5, decimal_places=2, blank=True, null=True)
    balwgt = models.DecimalField(max_digits=12, decimal_places=3, blank=True, null=True)
    remark = models.CharField(max_length=30, blank=True, null=True)
    hmc = models.DecimalField(max_digits=8, decimal_places=2, blank=True, null=True)
    prevrate = models.DecimalField(max_digits=9, decimal_places=2, blank=True, null=True)
    model = models.CharField(max_length=15, blank=True, null=True)
    submodel = models.CharField(max_length=15, blank=True, null=True)
    actwgt = models.DecimalField(max_digits=12, decimal_places=3, blank=True, null=True)
    batch = models.CharField(max_length=10, blank=True, null=True)
    mud = models.DecimalField(max_digits=6, decimal_places=3, blank=True, null=True)
    tp = models.DecimalField(max_digits=6, decimal_places=3, blank=True, null=True)
    sva = models.DecimalField(max_digits=5, decimal_places=2, blank=True, null=True)
    sstprice = models.DecimalField(max_digits=8, decimal_places=2, blank=True, null=True)
    goldvalue = models.DecimalField(max_digits=10, decimal_places=2, blank=True, null=True)
    totamt = models.DecimalField(max_digits=10, decimal_places=2, blank=True, null=True)
    rate = models.DecimalField(max_digits=8, decimal_places=2, blank=True, null=True)
    grate = models.DecimalField(max_digits=8, decimal_places=2, blank=True, null=True)
    gamount = models.DecimalField(max_digits=10, decimal_places=2, blank=True, null=True)

    class Meta:
        managed = False
        db_table = 'smithd'


class Smithm(models.Model):
    slno = models.DecimalField(primary_key=True, max_digits=10, decimal_places=0)
    docno = models.CharField(max_length=10, blank=True, null=True)
    tdate = models.DateField(blank=True, null=True)
    ttime = models.TimeField(blank=True, null=True)
    smithcode = models.CharField(max_length=8, blank=True, null=True)
    tmcharge = models.DecimalField(max_digits=10, decimal_places=2, blank=True, null=True)
    pamt = models.DecimalField(max_digits=10, decimal_places=2, blank=True, null=True)
    status = models.SmallIntegerField(blank=True, null=True)
    control = models.SmallIntegerField(blank=True, null=True)
    rate = models.DecimalField(max_digits=8, decimal_places=3, blank=True, null=True)
    rmno = models.CharField(max_length=10, blank=True, null=True)
    smcode = models.CharField(max_length=10, blank=True, null=True)
    person = models.CharField(max_length=20, blank=True, null=True)
    jewlcode = models.CharField(max_length=10, blank=True, null=True)
    ic = models.CharField(max_length=5, blank=True, null=True)
    opwgt = models.DecimalField(max_digits=12, decimal_places=3, blank=True, null=True)
    opamt = models.DecimalField(max_digits=12, decimal_places=2, blank=True, null=True)
    docno2 = models.CharField(max_length=10, blank=True, null=True)
    refno = models.CharField(max_length=10, blank=True, null=True)
    doctype = models.CharField(max_length=2, blank=True, null=True)
    duedate = models.DateField(blank=True, null=True)
    transportmode = models.CharField(max_length=15, blank=True, null=True)
    vehno = models.CharField(max_length=15, blank=True, null=True)
    purpose = models.CharField(max_length=20, blank=True, null=True)
    note = models.CharField(max_length=200, blank=True, null=True)
    tcsperc = models.DecimalField(max_digits=6, decimal_places=3, blank=True, null=True)
    tcsamt = models.DecimalField(max_digits=10, decimal_places=2, blank=True, null=True)
    tdsperc = models.DecimalField(max_digits=5, decimal_places=2, blank=True, null=True)
    tdsamt = models.DecimalField(max_digits=8, decimal_places=2, blank=True, null=True)
    acidcharge = models.DecimalField(max_digits=8, decimal_places=2, blank=True, null=True)
    discount = models.DecimalField(max_digits=8, decimal_places=2, blank=True, null=True)
    lotno = models.CharField(max_length=10, blank=True, null=True)
    taxperc = models.DecimalField(max_digits=5, decimal_places=2, blank=True, null=True)
    taxamt = models.DecimalField(max_digits=8, decimal_places=2, blank=True, null=True)
    interstate = models.CharField(max_length=1, blank=True, null=True)
    taxreverse = models.CharField(max_length=1, blank=True, null=True)
    statecode = models.CharField(max_length=10, blank=True, null=True)
    placeos = models.CharField(max_length=40, blank=True, null=True)
    trantype = models.CharField(max_length=5, blank=True, null=True)
    netamt = models.DecimalField(max_digits=12, decimal_places=2, blank=True, null=True)

    class Meta:
        managed = False
        db_table = 'smithm'


class Smithnewwrk(models.Model):
    tdate = models.DateField(blank=True, null=True)
    smithcode = models.CharField(max_length=10, blank=True, null=True)
    ordno = models.CharField(max_length=10, blank=True, null=True)
    party = models.CharField(max_length=30, blank=True, null=True)
    part = models.CharField(max_length=50, blank=True, null=True)
    status = models.SmallIntegerField(blank=True, null=True)
    control = models.SmallIntegerField(blank=True, null=True)
    icode = models.CharField(max_length=10, blank=True, null=True)
    qty = models.IntegerField(blank=True, null=True)
    weight = models.DecimalField(max_digits=9, decimal_places=3, blank=True, null=True)
    sno = models.AutoField(primary_key=True)
    sno2 = models.IntegerField(blank=True, null=True)

    class Meta:
        managed = False
        db_table = 'smithnewwrk'


class Smithsusp(models.Model):
    tdate = models.DateField()
    smithcode = models.CharField(max_length=10, blank=True, null=True)
    smithname = models.CharField(max_length=30, blank=True, null=True)
    icode = models.CharField(max_length=10, blank=True, null=True)
    iname = models.CharField(max_length=30, blank=True, null=True)
    qty = models.IntegerField(blank=True, null=True)
    weight = models.DecimalField(max_digits=9, decimal_places=3, blank=True, null=True)
    part = models.CharField(max_length=50, blank=True, null=True)
    sno = models.AutoField(primary_key=True)
    ttype = models.CharField(max_length=1, blank=True, null=True)

    class Meta:
        managed = False
        db_table = 'smithsusp'


class Wsrefinery(models.Model):
    slno = models.DecimalField(primary_key=True, max_digits=10, decimal_places=0)
    docno = models.CharField(max_length=10, blank=True, null=True)
    tdate = models.DateField(blank=True, null=True)
    icode = models.CharField(max_length=10, blank=True, null=True)
    touch = models.DecimalField(max_digits=6, decimal_places=2, blank=True, null=True)
    masswgt = models.DecimalField(max_digits=10, decimal_places=3, blank=True, null=True)
    standwgt = models.DecimalField(max_digits=10, decimal_places=3, blank=True, null=True)
    wrktouch = models.DecimalField(max_digits=6, decimal_places=2, blank=True, null=True)
    wrkwgt = models.DecimalField(max_digits=10, decimal_places=3, blank=True, null=True)
    wrkdiff = models.DecimalField(max_digits=8, decimal_places=3, blank=True, null=True)
    actualdiff = models.DecimalField(max_digits=8, decimal_places=3, blank=True, null=True)
    sno = models.SmallIntegerField(blank=True, null=True)
    control = models.SmallIntegerField(blank=True, null=True)
    gr = models.CharField(max_length=1, blank=True, null=True)
    mark = models.CharField(max_length=1, blank=True, null=True)

    class Meta:
        managed = False
        db_table = 'wsrefinery'


class Wstg(models.Model):
    cno = models.IntegerField(primary_key=True)
    llimit = models.DecimalField(max_digits=7, decimal_places=3, blank=True, null=True)
    ulimit = models.DecimalField(max_digits=7, decimal_places=3, blank=True, null=True)
    wastage = models.DecimalField(max_digits=7, decimal_places=3, blank=True, null=True)

    class Meta:
        managed = False
        db_table = 'wstg'


class Wstgtable(models.Model):
    code = models.CharField(primary_key=True, max_length=10)
    weight1 = models.DecimalField(max_digits=9, decimal_places=3, blank=True, null=True)
    weight2 = models.DecimalField(max_digits=9, decimal_places=3, blank=True, null=True)
    wastage = models.DecimalField(max_digits=9, decimal_places=3, blank=True, null=True)
    perc = models.DecimalField(max_digits=5, decimal_places=2, blank=True, null=True)
    iqtype = models.CharField(max_length=10, blank=True, null=True)

    class Meta:
        managed = False
        db_table = 'wstgtable'
