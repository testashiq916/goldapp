from django.db import models


class Appoint(models.Model):
    slno = models.DecimalField(primary_key=True, max_digits=10, decimal_places=0)
    tdatetime = models.DateTimeField(blank=True, null=True)
    desc = models.CharField(max_length=60, blank=True, null=True)

    class Meta:
        managed = False
        db_table = 'appoint'


class Clients(models.Model):
    code = models.CharField(primary_key=True, max_length=8)
    name = models.CharField(max_length=30)
    addr1 = models.CharField(max_length=30, blank=True, null=True)
    addr2 = models.CharField(max_length=30, blank=True, null=True)
    addr3 = models.CharField(max_length=30, blank=True, null=True)
    city = models.CharField(max_length=20, blank=True, null=True)
    telephone = models.CharField(max_length=25, blank=True, null=True)
    ctype = models.CharField(max_length=1, blank=True, null=True)
    opbalance = models.DecimalField(max_digits=12, decimal_places=2, blank=True, null=True)
    opbalanceb = models.DecimalField(max_digits=12, decimal_places=2, blank=True, null=True)
    control = models.SmallIntegerField(blank=True, null=True)
    salary = models.DecimalField(max_digits=9, decimal_places=2, blank=True, null=True)
    removed = models.SmallIntegerField(blank=True, null=True)
    cocode = models.CharField(max_length=10, blank=True, null=True)
    prn = models.SmallIntegerField(blank=True, null=True)
    grp = models.CharField(max_length=10, blank=True, null=True)
    adate = models.DateField(blank=True, null=True)
    duedate = models.DateField(blank=True, null=True)
    cutrate = models.DecimalField(max_digits=8, decimal_places=2, blank=True, null=True)
    mobile = models.CharField(max_length=25, blank=True, null=True)
    route = models.CharField(max_length=10, blank=True, null=True)
    homemobile = models.CharField(max_length=15, blank=True, null=True)
    email = models.CharField(max_length=40, blank=True, null=True)
    smcode = models.CharField(max_length=10, blank=True, null=True)
    colncomn = models.DecimalField(max_digits=5, decimal_places=2, blank=True, null=True)
    opweight = models.DecimalField(max_digits=10, decimal_places=3, blank=True, null=True)
    billdate = models.DateField(blank=True, null=True)
    idno = models.CharField(max_length=10, blank=True, null=True)
    religion = models.CharField(max_length=10, blank=True, null=True)
    note = models.CharField(max_length=100, blank=True, null=True)
    coparty = models.CharField(max_length=1, blank=True, null=True)
    carea = models.CharField(max_length=10, blank=True, null=True)
    pcard = models.CharField(max_length=10, blank=True, null=True)
    opdepwgtbal = models.DecimalField(max_digits=10, decimal_places=3, blank=True, null=True)
    tin = models.CharField(max_length=20, blank=True, null=True)
    cst = models.CharField(max_length=20, blank=True, null=True)
    clwgt = models.DecimalField(max_digits=10, decimal_places=3, blank=True, null=True)
    lpline = models.IntegerField(blank=True, null=True)
    lpslno = models.DecimalField(max_digits=10, decimal_places=0, blank=True, null=True)
    lpsno = models.DecimalField(max_digits=10, decimal_places=0, blank=True, null=True)
    pospwd = models.CharField(max_length=10, blank=True, null=True)
    agent = models.CharField(max_length=1, blank=True, null=True)
    state = models.CharField(max_length=10, blank=True, null=True)
    panadhar = models.CharField(max_length=20, blank=True, null=True)
    oppcardpoints = models.DecimalField(max_digits=10, decimal_places=2, blank=True, null=True)
    pcardno = models.CharField(max_length=12, blank=True, null=True)
    approval = models.CharField(max_length=1, blank=True, null=True)
    pin = models.CharField(max_length=10, blank=True, null=True)
    distance = models.IntegerField(blank=True, null=True)
    ppno = models.CharField(max_length=15, blank=True, null=True)
    nationality = models.CharField(max_length=30, blank=True, null=True)
    resident = models.CharField(max_length=2, blank=True, null=True)
    dob = models.DateField(blank=True, null=True)

    class Meta:
        managed = False
        db_table = 'clients'


class ClientsAdvanced(models.Model):
    code = models.CharField(primary_key=True, max_length=10)
    pincode = models.CharField(max_length=10, blank=True, null=True)
    fax = models.CharField(max_length=15, blank=True, null=True)
    mobile = models.CharField(max_length=20, blank=True, null=True)
    email = models.CharField(max_length=30, blank=True, null=True)
    relationperiod = models.CharField(max_length=10, blank=True, null=True)
    relationway = models.CharField(max_length=20, blank=True, null=True)
    relationwayname = models.CharField(max_length=40, blank=True, null=True)
    relationbases = models.CharField(max_length=20, blank=True, null=True)
    relationbasesname = models.CharField(max_length=40, blank=True, null=True)
    purchasepurpose = models.CharField(max_length=20, blank=True, null=True)
    purchasecategory = models.CharField(max_length=10, blank=True, null=True)
    purchasetype = models.CharField(max_length=20, blank=True, null=True)
    working = models.CharField(max_length=20, blank=True, null=True)
    comments = models.CharField(max_length=40, blank=True, null=True)
    promptorder = models.CharField(max_length=5, blank=True, null=True)
    dtmarriage = models.DateField(blank=True, null=True)
    dtengagement = models.DateField(blank=True, null=True)
    dtbirthday = models.DateField(blank=True, null=True)
    grateinform = models.CharField(max_length=5, blank=True, null=True)
    staffperf = models.CharField(max_length=10, blank=True, null=True)
    properf = models.CharField(max_length=10, blank=True, null=True)
    respperf = models.CharField(max_length=10, blank=True, null=True)
    speedperf = models.CharField(max_length=10, blank=True, null=True)
    cleanperf = models.CharField(max_length=10, blank=True, null=True)
    facilityperf = models.CharField(max_length=10, blank=True, null=True)
    parkfacilityperf = models.CharField(max_length=10, blank=True, null=True)
    contshop = models.CharField(max_length=5, blank=True, null=True)
    recommend = models.CharField(max_length=5, blank=True, null=True)
    religion = models.CharField(max_length=15, blank=True, null=True)
    carea = models.CharField(max_length=10, blank=True, null=True)
    marks = models.DecimalField(max_digits=6, decimal_places=2, blank=True, null=True)

    class Meta:
        managed = False
        db_table = 'clients_advanced'


class ClientsKuridet(models.Model):
    code = models.CharField(primary_key=True, max_length=10)
    startdate = models.DateField()
    instnos = models.SmallIntegerField(blank=True, null=True)
    instamt = models.DecimalField(max_digits=8, decimal_places=2, blank=True, null=True)
    totamt = models.DecimalField(max_digits=10, decimal_places=2, blank=True, null=True)
    bonus = models.DecimalField(max_digits=8, decimal_places=2, blank=True, null=True)
    finished = models.CharField(max_length=1, blank=True, null=True)
    finisheddate = models.DateField(blank=True, null=True)
    kuritype = models.CharField(max_length=10, blank=True, null=True)
    intrate = models.DecimalField(max_digits=5, decimal_places=2, blank=True, null=True)
    colntype = models.CharField(max_length=2, blank=True, null=True)
    colnagent = models.CharField(max_length=10, blank=True, null=True)
    matdate = models.DateField(blank=True, null=True)
    wadate = models.DateField(blank=True, null=True)
    bdate = models.DateField(blank=True, null=True)
    custlinkac = models.CharField(max_length=10, blank=True, null=True)
    collnmaxamt = models.DecimalField(max_digits=10, decimal_places=2, blank=True, null=True)
    collnminamt = models.DecimalField(max_digits=10, decimal_places=2, blank=True, null=True)
    bankacno = models.CharField(max_length=20, blank=True, null=True)
    bankname = models.CharField(max_length=60, blank=True, null=True)
    bankifsc = models.CharField(max_length=20, blank=True, null=True)
    nomname = models.CharField(max_length=30, blank=True, null=True)
    nomaddr = models.CharField(max_length=60, blank=True, null=True)
    nomrelation = models.CharField(max_length=10, blank=True, null=True)
    showwgtdet = models.CharField(max_length=1, blank=True, null=True)
    opwgt = models.DecimalField(max_digits=10, decimal_places=3, blank=True, null=True)
    opwgtb = models.DecimalField(max_digits=10, decimal_places=3, blank=True, null=True)
    collnopbal = models.DecimalField(max_digits=10, decimal_places=2, blank=True, null=True)

    class Meta:
        managed = False
        db_table = 'clients_kuridet'


class Clientsgrp(models.Model):
    code = models.CharField(primary_key=True, max_length=10)
    name = models.CharField(max_length=30, blank=True, null=True)

    class Meta:
        managed = False
        db_table = 'clientsgrp'


class Clientsgs(models.Model):
    code = models.CharField(primary_key=True, max_length=8)
    opweight = models.DecimalField(max_digits=10, decimal_places=3, blank=True, null=True)
    opweightb = models.DecimalField(max_digits=10, decimal_places=3, blank=True, null=True)
    wastage = models.DecimalField(max_digits=6, decimal_places=3, blank=True, null=True)
    mcrate = models.DecimalField(max_digits=6, decimal_places=2, blank=True, null=True)
    ctype = models.CharField(max_length=1, blank=True, null=True)
    opwgtamt = models.DecimalField(max_digits=10, decimal_places=2, blank=True, null=True)
    opwgtamtb = models.DecimalField(max_digits=10, decimal_places=2, blank=True, null=True)
    acin24ct = models.SmallIntegerField(blank=True, null=True)
    deftouch = models.DecimalField(max_digits=5, decimal_places=2, blank=True, null=True)
    convtouch = models.DecimalField(max_digits=5, decimal_places=2, blank=True, null=True)
    email = models.CharField(max_length=40, blank=True, null=True)
    decround = models.SmallIntegerField(blank=True, null=True)
    roundup = models.DecimalField(max_digits=5, decimal_places=3, blank=True, null=True)
    mcdecround = models.SmallIntegerField(blank=True, null=True)
    intamt = models.DecimalField(max_digits=10, decimal_places=2, blank=True, null=True)
    intwgt = models.DecimalField(max_digits=8, decimal_places=3, blank=True, null=True)
    silver = models.CharField(max_length=1, blank=True, null=True)
    stocktouch = models.DecimalField(max_digits=5, decimal_places=2, blank=True, null=True)
    branch = models.CharField(max_length=1, blank=True, null=True)

    class Meta:
        managed = False
        db_table = 'clientsgs'


class Clientspict(models.Model):
    code = models.CharField(primary_key=True, max_length=10)
    pict = models.TextField(blank=True, null=True)
    ptype = models.CharField(max_length=10, blank=True, null=True)

    class Meta:
        managed = False
        db_table = 'clientspict'


class Comntable(models.Model):
    cat = models.CharField(primary_key=True, max_length=5)
    minsalewgt = models.DecimalField(max_digits=10, decimal_places=3, blank=True, null=True)
    ratepergm = models.DecimalField(max_digits=8, decimal_places=2, blank=True, null=True)
    perc = models.DecimalField(max_digits=5, decimal_places=2, blank=True, null=True)

    class Meta:
        managed = False
        db_table = 'comntable'


class Copartylimit(models.Model):
    code = models.CharField(primary_key=True, max_length=10)
    maxamt = models.DecimalField(max_digits=12, decimal_places=2)
    maxwgt = models.DecimalField(max_digits=10, decimal_places=3, blank=True, null=True)

    class Meta:
        managed = False
        db_table = 'copartylimit'


class Followups(models.Model):
    pk = models.CompositePrimaryKey('tdate', 'ttime')
    tdate = models.DateField()
    ttime = models.TimeField()
    sman = models.CharField(max_length=10)
    party = models.CharField(max_length=10)
    note = models.CharField(max_length=500)

    class Meta:
        managed = False
        db_table = 'followups'


class Incharge(models.Model):
    code = models.CharField(primary_key=True, max_length=5)
    name = models.CharField(max_length=30)

    class Meta:
        managed = False
        db_table = 'incharge'


class Phonebook(models.Model):
    pk = models.CompositePrimaryKey('no', 'name')
    no = models.CharField(max_length=5)
    name = models.CharField(max_length=30)
    resaddress = models.CharField(max_length=60, blank=True, null=True)
    resphone = models.CharField(max_length=30, blank=True, null=True)
    offaddress = models.CharField(max_length=60, blank=True, null=True)
    offphone = models.CharField(max_length=30, blank=True, null=True)
    mobile = models.CharField(max_length=20, blank=True, null=True)
    email = models.CharField(max_length=30, blank=True, null=True)
    ptype = models.CharField(max_length=10, blank=True, null=True)
    grp = models.CharField(max_length=10, blank=True, null=True)

    class Meta:
        managed = False
        db_table = 'phonebook'


class Phoneptype(models.Model):
    code = models.CharField(primary_key=True, max_length=10)
    name = models.CharField(max_length=30, blank=True, null=True)

    class Meta:
        managed = False
        db_table = 'phoneptype'
