<?php

namespace App\Repository;

use App\Entity\QrCode;
use App\Entity\Scan;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ScanRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Scan::class);
    }

    // ── Enregistrer un scan + incrémenter le compteur ─────────────────
    public function record(QrCode $qrCode, string $ip, string $userAgent): Scan
    {
        $scan = new Scan();
        $scan->setQrCode($qrCode);
        $scan->setIpAdresse($ip);
        //$scan->setUserAgent($userAgent);
        $scan->setAppareil($this->detectDevice($userAgent));
        $scan->setNavigateur($this->detectBrowser($userAgent));

        $em = $this->getEntityManager();
        $em->persist($scan);
        $qrCode->setCompteurScans(($qrCode->getCompteurScans() ?? 0) + 1);
        $em->flush();

        return $scan;
    }

    // ── Scans d'un QrCode (les N derniers) ────────────────────────────
    public function findByQrCode(QrCode $qrCode, int $limit = 100): array
    {
        return $this->createQueryBuilder('s')
            ->where('s.qrCode = :qr')
            ->setParameter('qr', $qrCode)
            ->orderBy('s.dateHeure', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    // ── Scans filtrés par période (EU-15) ─────────────────────────────
    public function findByQrCodeAndPeriod(QrCode $qrCode, \DateTime $from, \DateTime $to): array
    {
        return $this->createQueryBuilder('s')
            ->where('s.qrCode = :qr')
            ->andWhere('s.dateHeure >= :from')
            ->andWhere('s.dateHeure <= :to')
            ->setParameter('qr',   $qrCode)
            ->setParameter('from', $from)
            ->setParameter('to',   $to)
            ->orderBy('s.dateHeure', 'DESC')
            ->getQuery()
            ->getResult();
    }

    // ── Scans par jour pour un QrCode (courbe 30j) ────────────────────
    // ✅ DATE_FORMAT au lieu de DATE() — compatible Doctrine DQL
    public function getScansPerDay(QrCode $qrCode, int $days = 30): array
    {
        $from = new \DateTime("-{$days} days");

        $rows = $this->createQueryBuilder('s')
            ->select("FUNCTION('DATE_FORMAT', s.dateHeure, '%Y-%m-%d') as jour, COUNT(s.id) as total")
            ->where('s.qrCode = :qr')
            ->andWhere('s.dateHeure >= :from')
            ->setParameter('qr',   $qrCode)
            ->setParameter('from', $from)
            ->groupBy('jour')
            ->orderBy('jour', 'ASC')
            ->getQuery()
            ->getScalarResult();

        $data = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = (new \DateTime("-{$i} days"))->format('Y-m-d');
            $data[$date] = 0;
        }
        foreach ($rows as $row) {
            $data[$row['jour']] = (int)$row['total'];
        }

        return $data;
    }

    // ── Scans par jour sur une plage personnalisée ────────────────────
    public function getScansPerDayForPeriod(QrCode $qrCode, \DateTime $from, \DateTime $to): array
    {
        $rows = $this->createQueryBuilder('s')
            ->select("FUNCTION('DATE_FORMAT', s.dateHeure, '%Y-%m-%d') as jour, COUNT(s.id) as total")
            ->where('s.qrCode = :qr')
            ->andWhere('s.dateHeure >= :from')
            ->andWhere('s.dateHeure <= :to')
            ->setParameter('qr',   $qrCode)
            ->setParameter('from', $from)
            ->setParameter('to',   $to)
            ->groupBy('jour')
            ->orderBy('jour', 'ASC')
            ->getQuery()
            ->getScalarResult();

        $data  = [];
        $diff  = $from->diff($to)->days;
        for ($i = 0; $i <= $diff; $i++) {
            $date = (clone $from)->modify("+{$i} days")->format('Y-m-d');
            $data[$date] = 0;
        }
        foreach ($rows as $row) {
            $data[$row['jour']] = (int)$row['total'];
        }

        return $data;
    }

    // ── Répartition par appareil ──────────────────────────────────────
    public function getDeviceStats(QrCode $qrCode): array
    {
        $rows = $this->createQueryBuilder('s')
            ->select('s.appareil as appareil, COUNT(s.id) as total')
            ->where('s.qrCode = :qr')
            ->setParameter('qr', $qrCode)
            ->groupBy('s.appareil')
            ->getQuery()
            ->getScalarResult();

        $result = ['mobile' => 0, 'desktop' => 0, 'tablet' => 0, 'unknown' => 0];
        foreach ($rows as $row) {
            $key = $row['appareil'] ?? 'unknown';
            $result[$key] = (int)$row['total'];
        }
        return $result;
    }

    // ── Répartition par navigateur ────────────────────────────────────
    public function getBrowserStats(QrCode $qrCode): array
    {
        $rows = $this->createQueryBuilder('s')
            ->select('s.navigateur as navigateur, COUNT(s.id) as total')
            ->where('s.qrCode = :qr')
            ->setParameter('qr', $qrCode)
            ->groupBy('s.navigateur')
            ->orderBy('total', 'DESC')
            ->setMaxResults(6)
            ->getQuery()
            ->getScalarResult();

        $result = [];
        foreach ($rows as $row) {
            $result[$row['navigateur'] ?? 'Autre'] = (int)$row['total'];
        }
        return $result;
    }

    // ── Stats globales pour un User ───────────────────────────────────
    // ✅ Scans today : comparaison par plage datetime (pas DATE())
    public function getGlobalStatsForUser(User $user): array
    {
        $todayStart = new \DateTime('today');
        $todayEnd   = new \DateTime('tomorrow');
        $weekStart  = new \DateTime('-7 days');

        $total = (int)$this->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->join('s.qrCode', 'q')
            ->where('q.user = :user')
            ->setParameter('user', $user)
            ->getQuery()->getSingleScalarResult();

        $todayCount = (int)$this->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->join('s.qrCode', 'q')
            ->where('q.user = :user')
            ->andWhere('s.dateHeure >= :start')
            ->andWhere('s.dateHeure < :end')
            ->setParameter('user',  $user)
            ->setParameter('start', $todayStart)
            ->setParameter('end',   $todayEnd)
            ->getQuery()->getSingleScalarResult();

        $weekCount = (int)$this->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->join('s.qrCode', 'q')
            ->where('q.user = :user')
            ->andWhere('s.dateHeure >= :week')
            ->setParameter('user', $user)
            ->setParameter('week', $weekStart)
            ->getQuery()->getSingleScalarResult();

        return ['total' => $total, 'today' => $todayCount, 'week' => $weekCount];
    }

    // ── Top QR codes par scans pour un User ──────────────────────────
    public function getTopQrCodes(User $user, int $limit = 5): array
    {
        return $this->createQueryBuilder('s')
            ->select('q.id, q.titre, q.type, COUNT(s.id) as totalScans')
            ->join('s.qrCode', 'q')
            ->where('q.user = :user')
            ->setParameter('user', $user)
            ->groupBy('q.id, q.titre, q.type')
            ->orderBy('totalScans', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getScalarResult();
    }

    // ── Courbe globale 30j pour un User ──────────────────────────────
    // ✅ DATE_FORMAT au lieu de DATE()
    public function getGlobalScansPerDay(User $user, int $days = 30): array
    {
        $from = new \DateTime("-{$days} days");

        $rows = $this->createQueryBuilder('s')
            ->select("FUNCTION('DATE_FORMAT', s.dateHeure, '%Y-%m-%d') as jour, COUNT(s.id) as total")
            ->join('s.qrCode', 'q')
            ->where('q.user = :user')
            ->andWhere('s.dateHeure >= :from')
            ->setParameter('user', $user)
            ->setParameter('from', $from)
            ->groupBy('jour')
            ->orderBy('jour', 'ASC')
            ->getQuery()
            ->getScalarResult();

        $data = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = (new \DateTime("-{$i} days"))->format('Y-m-d');
            $data[$date] = 0;
        }
        foreach ($rows as $row) {
            $data[$row['jour']] = (int)$row['total'];
        }

        return $data;
    }

    // ── Helpers détection device / navigateur ─────────────────────────
    private function detectDevice(string $ua): string
    {
        $ua = strtolower($ua);
        if (str_contains($ua, 'tablet') || str_contains($ua, 'ipad'))    return 'tablet';
        if (str_contains($ua, 'mobile') || str_contains($ua, 'android') || str_contains($ua, 'iphone')) return 'mobile';
        return 'desktop';
    }

    private function detectBrowser(string $ua): string
    {
        $ua = strtolower($ua);
        if (str_contains($ua, 'edg'))     return 'Edge';
        if (str_contains($ua, 'opr') || str_contains($ua, 'opera')) return 'Opera';
        if (str_contains($ua, 'chrome'))  return 'Chrome';
        if (str_contains($ua, 'firefox')) return 'Firefox';
        if (str_contains($ua, 'safari'))  return 'Safari';
        if (str_contains($ua, 'msie') || str_contains($ua, 'trident')) return 'IE';
        return 'Autre';
    }
}