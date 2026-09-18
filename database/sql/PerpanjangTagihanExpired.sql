-- Perpanjang otomatis semua tagihan yang ExpDate sudah kadaluarsa.
-- Aturan tanggal (bukan InputTagihan):
--   hari <= 20 -> tgl 20 bulan ini 23:59:59
--   hari >  20 -> tgl 20 bulan depan 23:59:59
--
-- p_CODE01 kosong / NULL = semua unit; terisi = filter scctcust.CODE01

DROP PROCEDURE IF EXISTS `PerpanjangTagihanExpired`;

DELIMITER $$

CREATE PROCEDURE `PerpanjangTagihanExpired`(
    IN p_CODE01 VARCHAR(20)
)
main: BEGIN
    DECLARE v_day INT DEFAULT 0;
    DECLARE v_NewExp DATETIME;
    DECLARE v_CODE01 VARCHAR(20);
    DECLARE v_updated INT DEFAULT 0;

    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;

    SET v_CODE01 = NULLIF(TRIM(IFNULL(p_CODE01, '')), '');
    SET v_day = DAY(NOW());

    IF v_day <= 20 THEN
        SET v_NewExp = STR_TO_DATE(
            CONCAT(DATE_FORMAT(CURDATE(), '%Y-%m-20'), ' 23:59:59'),
            '%Y-%m-%d %H:%i:%s'
        );
    ELSE
        SET v_NewExp = STR_TO_DATE(
            CONCAT(DATE_FORMAT(DATE_ADD(CURDATE(), INTERVAL 1 MONTH), '%Y-%m-20'), ' 23:59:59'),
            '%Y-%m-%d %H:%i:%s'
        );
    END IF;

    START TRANSACTION;

    UPDATE scctbill b
    INNER JOIN scctcust c ON c.CUSTID = b.CUSTID
    SET b.ExpDate = v_NewExp
    WHERE b.ExpDate IS NOT NULL
      AND b.ExpDate < NOW()
      AND b.FSTSBolehBayar = 1
      AND (
            b.PAIDST = 0
            OR b.PAIDST IS NULL
            OR CAST(COALESCE(b.PAYMENTLEFT, b.BILLAM - COALESCE(b.BILLPAID, 0), 0) AS SIGNED) > 0
          )
      AND CAST(COALESCE(c.STCUST, 0) AS SIGNED) = 1
      AND (v_CODE01 IS NULL OR c.CODE01 = v_CODE01);

    SET v_updated = ROW_COUNT();

    COMMIT;

    SELECT v_updated AS updated_count, v_NewExp AS new_exp_date;
END$$

DELIMITER ;
