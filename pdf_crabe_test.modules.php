<?php
/* Copyright (C) 2004-2014	Laurent Destailleur	<eldy@users.sourceforge.net>
 * Copyright (C) 2005-2012	Regis Houssin		<regis.houssin@inodbox.com>
 * Copyright (C) 2008		Raphael Bertrand		<raphael.bertrand@resultic.fr>
 * Copyright (C) 2010-2014	Juanjo Menent		<jmenent@2byte.es>
 * Copyright (C) 2012		Christophe Battarel	<christophe.battarel@altairis.fr>
 * Copyright (C) 2012		Cédric Salvador		<csalvador@gpcsolutions.fr>
 * Copyright (C) 2012-2014	Raphaël Doursenaud	<rdoursenaud@gpcsolutions.fr>
 * Copyright (C) 2015		Marcos García		<marcosgdf@gmail.com>
 * Copyright (C) 2017-2018	Ferran Marcet		<fmarcet@2byte.es>
 * Copyright (C) 2018       Frédéric France     <frederic.france@netlogic.fr>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 * or see https://www.gnu.org/
 */

/**
 *	\file       htdocs/core/modules/facture/doc/pdf_crabe.modules.php
 *	\ingroup    facture
 *	\brief      File of class to generate customers invoices from crabe model
 */

require_once DOL_DOCUMENT_ROOT.'/core/modules/facture/modules_facture.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/company.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/functions2.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/pdf.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/commonstickergenerator.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/extrafields.class.php';

/**
 *	Class to generate the customer invoice PDF with template Crabe
 */
class pdf_crabe_test extends ModelePDFFactures
{
     /**
     * @var DoliDb Database handler
     */
    public $db;

	/**
     * @var string model name
     */
    public $name;

	/**
     * @var string model description (short text)
     */
    public $description;

    /**
     * @var int 	Save the name of generated file as the main doc when generating a doc with this template
     */
    public $update_main_doc_field;

	/**
     * @var string document type
     */
    public $type;

	/**
     * @var array Minimum version of PHP required by module.
     * e.g.: PHP ≥ 5.5 = array(5, 5)
     */
	public $phpmin = array(5, 5);

	/**
     * Dolibarr version of the loaded document
     * @var string
     */
	public $version = 'dolibarr';

	/**
     * @var int page_largeur
     */
    public $page_largeur;

	/**
     * @var int page_hauteur
     */
    public $page_hauteur;

	/**
     * @var array format
     */
    public $format;

	/**
     * @var int marge_gauche
     */
	public $marge_gauche;

	/**
     * @var int marge_droite
     */
	public $marge_droite;

	/**
     * @var int marge_haute
     */
	public $marge_haute;

	/**
     * @var int marge_basse
     */
	public $marge_basse;

	/**
	 * Issuer
	 * @var Societe object that emits
	 */
	public $emetteur;

	/**
	 * @var bool Situation invoice type
	 */
	public $situationinvoice;

	/**
	 * @var float X position for the situation progress column
	 */
	public $posxprogress;


	/**
	 *	Constructor
	 *
	 *  @param		DoliDB		$db      Database handler
	 */
	public function __construct($db)
	{
		global $conf, $langs, $mysoc;

		// Translations
		$langs->loadLangs(array("main", "bills"));

		$this->db = $db;
		$this->name = "crabe test";
		$this->description = $langs->trans('PDFCrabeDescription');
		$this->update_main_doc_field = 1; // Save the name of generated file as the main doc when generating a doc with this template

		// Dimension page
		$this->type = 'pdf';
		$formatarray = pdf_getFormat();
		$this->page_largeur = $formatarray['width'];
		$this->page_hauteur = $formatarray['height'];
		$this->format = array($this->page_largeur, $this->page_hauteur);
		$this->marge_gauche = isset($conf->global->MAIN_PDF_MARGIN_LEFT) ? $conf->global->MAIN_PDF_MARGIN_LEFT : 10;
		$this->marge_droite = isset($conf->global->MAIN_PDF_MARGIN_RIGHT) ? $conf->global->MAIN_PDF_MARGIN_RIGHT : 10;
		$this->marge_haute = isset($conf->global->MAIN_PDF_MARGIN_TOP) ? $conf->global->MAIN_PDF_MARGIN_TOP : 10;
		$this->marge_basse = isset($conf->global->MAIN_PDF_MARGIN_BOTTOM) ? $conf->global->MAIN_PDF_MARGIN_BOTTOM : 10;

		$this->option_logo = 1; // Display logo
		$this->option_tva = 1; // Manage the vat option FACTURE_TVAOPTION
		$this->option_modereg = 1; // Display payment mode
		$this->option_condreg = 1; // Display payment terms
		$this->option_codeproduitservice = 1; // Display product-service code
		$this->option_multilang = 1; // Available in several languages
		$this->option_escompte = 1; // Displays if there has been a discount
		$this->option_credit_note = 1; // Support credit notes
		$this->option_freetext = 1; // Support add of a personalised text
		$this->option_draft_watermark = 1; // Support add of a watermark on drafts

		$this->franchise = !$mysoc->tva_assuj;

		// Get source company
		$this->emetteur = $mysoc;
		if (empty($this->emetteur->country_code)) $this->emetteur->country_code = substr($langs->defaultlang, -2); // By default, if was not defined

		// Define position of columns
		$this->posxdesc = $this->marge_gauche + 1;
		if (!empty($conf->global->PRODUCT_USE_UNITS))
		{
			$this->posxtva = 101;
			$this->posxup = 118;
			$this->posxqty = 135;
			$this->posxunit = 151;
		}
		else
		{
			$this->posxtva = 110;
			$this->posxup = 126;
			$this->posxqty = 145;
			$this->posxunit = 162;
		}
		$this->posxprogress = 151; // Only displayed for situation invoices
		$this->posxdiscount = 162;
		$this->posxprogress = 174;
		$this->postotalht = 174;
		if (!empty($conf->global->MAIN_GENERATE_DOCUMENTS_WITHOUT_VAT) || !empty($conf->global->MAIN_GENERATE_DOCUMENTS_WITHOUT_VAT_COLUMN)) $this->posxtva = $this->posxup;
		$this->posxpicture = $this->posxtva - (empty($conf->global->MAIN_DOCUMENTS_WITH_PICTURE_WIDTH) ? 20 : $conf->global->MAIN_DOCUMENTS_WITH_PICTURE_WIDTH); // width of images
		if ($this->page_largeur < 210) // To work with US executive format
		{
		    $this->posxpicture -= 20;
		    $this->posxtva -= 20;
		    $this->posxup -= 20;
		    $this->posxqty -= 20;
		    $this->posxunit -= 20;
		    $this->posxdiscount -= 20;
		    $this->posxprogress -= 20;
		    $this->postotalht -= 20;
		}

		$this->tva = array();
		$this->localtax1 = array();
		$this->localtax2 = array();
		$this->atleastoneratenotnull = 0;
		$this->atleastonediscount = 0;
		$this->situationinvoice = false;
	}


    // phpcs:disable PEAR.NamingConventions.ValidFunctionName.ScopeNotCamelCaps
	/**
     *  Function to build pdf onto disk
     *
     *  @param		Object		$object				Object to generate
     *  @param		Translate	$outputlangs		Lang output object
     *  @param		string		$srctemplatepath	Full path of source filename for generator using a template file
     *  @param		int			$hidedetails		Do not show line details
     *  @param		int			$hidedesc			Do not show desc
     *  @param		int			$hideref			Do not show ref
     *  @return     int         	    			1=OK, 0=KO
	 */
    public function write_file($object, $outputlangs, $srctemplatepath = '', $hidedetails = 0, $hidedesc = 0, $hideref = 0)
	{
        // phpcs:enable
		global $user, $langs, $conf, $mysoc, $hookmanager, $nblines;

		dol_syslog("write_file outputlangs->defaultlang=".(is_object($outputlangs) ? $outputlangs->defaultlang : 'null'));

		if (!is_object($outputlangs)) $outputlangs = $langs;
		// For backward compatibility with FPDF, force output charset to ISO, because FPDF expect text to be encoded in ISO
		if (!empty($conf->global->MAIN_USE_FPDF)) $outputlangs->charset_output = 'ISO-8859-1';

		// Load translation files required by the page
		$outputlangs->loadLangs(array("main", "bills", "products", "dict", "companies"));

		$nblines = count($object->lines);

		// Loop on each lines to detect if there is at least one image to show
		$realpatharray = array();
		if (!empty($conf->global->MAIN_GENERATE_INVOICES_WITH_PICTURE))
		{
			for ($i = 0; $i < $nblines; $i++)
			{
				
				if (empty($object->lines[$i]->fk_product)) continue;

				$objphoto = new Product($this->db);
				$objphoto->fetch($object->lines[$i]->fk_product);

				$pdir = get_exdir($object->lines[$i]->fk_product, 2, 0, 0, $objphoto, 'product').$object->lines[$i]->fk_product."/photos/";
				$dir = $conf->product->dir_output.'/'.$pdir;

				$realpath = '';
				foreach ($objphoto->liste_photos($dir, 1) as $key => $obj)
				{
					$filename = $obj['photo'];
					//if ($obj['photo_vignette']) $filename='thumbs/'.$obj['photo_vignette'];
					$realpath = $dir.$filename;
					break;
				}

				if ($realpath) $realpatharray[$i] = $realpath;
			}
		}
		if (count($realpatharray) == 0) $this->posxpicture = $this->posxtva;

		if ($conf->facture->dir_output)
		{
			$object->fetch_thirdparty();
            $extrafieldsline = new ExtraFields($this->db);
$extralabelsline=$extrafieldsline->fetch_name_optionals_label($object->table_element_line);
			$deja_regle = $object->getSommePaiement(($conf->multicurrency->enabled && $object->multicurrency_tx != 1) ? 1 : 0);
			$amount_credit_notes_included = $object->getSumCreditNotesUsed(($conf->multicurrency->enabled && $object->multicurrency_tx != 1) ? 1 : 0);
			$amount_deposits_included = $object->getSumDepositsUsed(($conf->multicurrency->enabled && $object->multicurrency_tx != 1) ? 1 : 0);

			// Definition of $dir and $file
			if ($object->specimen)
			{
				$dir = $conf->facture->dir_output;
				$file = $dir."/SPECIMEN.pdf";
			}
			else
			{
				$objectref = dol_sanitizeFileName($object->ref);
				$dir = $conf->facture->dir_output."/".$objectref;
				$file = $dir."/".$objectref.".pdf";
			}
			if (!file_exists($dir))
			{
				if (dol_mkdir($dir) < 0)
				{
					$this->error = $langs->transnoentities("ErrorCanNotCreateDir", $dir);
					return 0;
				}
			}

			if (file_exists($dir))
			{
				// Add pdfgeneration hook
				if (!is_object($hookmanager))
				{
					include_once DOL_DOCUMENT_ROOT.'/core/class/hookmanager.class.php';
					$hookmanager = new HookManager($this->db);
				}
				$hookmanager->initHooks(array('pdfgeneration'));
				$parameters = array('file'=>$file, 'object'=>$object, 'outputlangs'=>$outputlangs);
				global $action;
				$reshook = $hookmanager->executeHooks('beforePDFCreation', $parameters, $object, $action); // Note that $action and $object may have been modified by some hooks

				// Set nblines with the new facture lines content after hook
				$nblines = count($object->lines);
				$nbpayments = count($object->getListOfPayments());

				// Create pdf instance
				$pdf = pdf_getInstance($this->format);
                $default_font_size = pdf_getPDFFontSize($outputlangs); // Must be after pdf_getInstance
                $pdf->SetAutoPageBreak(1, 0);

                $heightforinfotot = 50 + (4 * $nbpayments); // Height reserved to output the info and total part and payment part
		        $heightforfreetext = (isset($conf->global->MAIN_PDF_FREETEXT_HEIGHT) ? $conf->global->MAIN_PDF_FREETEXT_HEIGHT : 5); // Height reserved to output the free text on last page
	            $heightforfooter = $this->marge_basse + 8; // Height reserved to output the footer (value include bottom margin)
	            if ($conf->global->MAIN_GENERATE_DOCUMENTS_SHOW_FOOT_DETAILS > 0) $heightforfooter += 6;

                if (class_exists('TCPDF'))
                {
                    $pdf->setPrintHeader(false);
                    $pdf->setPrintFooter(false);
                }
                $pdf->SetFont(pdf_getPDFFont($outputlangs));

                // Set path to the background PDF File
                if (!empty($conf->global->MAIN_ADD_PDF_BACKGROUND))
                {
                	$pagecount = $pdf->setSourceFile($conf->mycompany->multidir_output[$object->entity].'/'.$conf->global->MAIN_ADD_PDF_BACKGROUND);
				    $tplidx = $pdf->importPage(1);
                }

				$pdf->Open();
				$pagenb = 0;
				$pdf->SetDrawColor(128, 128, 128);

				$pdf->SetTitle($outputlangs->convToOutputCharset($object->ref));
				$pdf->SetSubject($outputlangs->transnoentities("PdfInvoiceTitle"));
				$pdf->SetCreator("Dolibarr ".DOL_VERSION);
				$pdf->SetAuthor($outputlangs->convToOutputCharset($user->getFullName($outputlangs)));
				$pdf->SetKeyWords($outputlangs->convToOutputCharset($object->ref)." ".$outputlangs->transnoentities("PdfInvoiceTitle")." ".$outputlangs->convToOutputCharset($object->thirdparty->name));
				if (!empty($conf->global->MAIN_DISABLE_PDF_COMPRESSION)) $pdf->SetCompression(false);

				$pdf->SetMargins($this->marge_gauche, $this->marge_haute, $this->marge_droite); // Left, Top, Right

				// Set $this->atleastonediscount if you have at least one discount
				for ($i = 0; $i < $nblines; $i++)
				{
					if ($object->lines[$i]->remise_percent)
					{
						$this->atleastonediscount++;
					}
				}
				if (empty($this->atleastonediscount))    // retreive space not used by discount
				{
				    $delta = ($this->posxprogress - $this->posxdiscount);
				    $this->posxpicture += $delta;
				    $this->posxtva += $delta;
				    $this->posxup += $delta;
				    $this->posxqty += $delta;
				    $this->posxunit += $delta;
				    $this->posxdiscount += $delta;
				    // post of fields after are not modified, stay at same position
				}

				$progress_width = 0;
				// Situation invoice handling
				if ($object->situation_cycle_ref && empty($conf->global->MAIN_PDF_HIDE_SITUATION))
				{
					$this->situationinvoice = true;
					$progress_width = 10;
					$this->posxpicture -= $progress_width;
					$this->posxtva -= $progress_width;
					$this->posxup -= $progress_width;
					$this->posxqty -= $progress_width;
					$this->posxunit -= $progress_width;
					$this->posxdiscount -= $progress_width;
					$this->posxprogress -= $progress_width;
				}

				// New page
				$pdf->AddPage();
				if (!empty($tplidx)) $pdf->useTemplate($tplidx);
				$pagenb++;

				$top_shift = $this->_pagehead($pdf, $object, 1, $outputlangs);
				$pdf->SetFont('', '', $default_font_size - 1);
				$pdf->MultiCell(0, 3, ''); // Set interline to 3
				$pdf->SetTextColor(0, 0, 0);

				$tab_top = 90 + $top_shift;
				$tab_top_newpage = (empty($conf->global->MAIN_PDF_DONOTREPEAT_HEAD) ? 42 + $top_shift : 10);

				// Incoterm
				if ($conf->incoterm->enabled)
				{
					$desc_incoterms = $object->getIncotermsForPDF();
					if ($desc_incoterms)
					{
						$tab_top -= 2;

						$pdf->SetFont('', '', $default_font_size - 1);
						$pdf->writeHTMLCell(190, 3, $this->posxdesc - 1, $tab_top - 1, dol_htmlentitiesbr($desc_incoterms), 0, 1);
						$nexY = $pdf->GetY();
						$height_incoterms = $nexY - $tab_top;

						// Rect takes a length in 3rd parameter
						$pdf->SetDrawColor(192, 192, 192);
						$pdf->Rect($this->marge_gauche, $tab_top - 1, $this->page_largeur - $this->marge_gauche - $this->marge_droite, $height_incoterms + 1);

						$tab_top = $nexY + 6;
					}
				}
				$extrafields = new ExtraFields($this->db);
$extralabels=$extrafields->fetch_name_optionals_label($object->table_element);
$object->fetch($rowid);
$object->fetch_optionals($rowid,$extralabels);
$pdf->writeHTMLCell (190,3, $this->posxdesc+160, $tab_top-17, $extrafields->showOutputField('salesexecutive', $object->array_options['options_salesexecutive']),0,1);
$pdf->writeHTMLCell (190,3, $this->posxdesc+160, $tab_top-13, $extrafields->showOutputField('customerpickup', $object->array_options['options_customerpickup']),0,1);
$pdf->SetFont('', 'B', $default_font_size - 1);
$pdf->writeHTMLCell (30,3, $this->posxdesc+66, $tab_top-31, $outputlangs->convToOutputCharset($object->array_options['options_jobaddress']),0,1);


				// Display notes
				$notetoshow = empty($object->note_public) ? '' : $object->note_public;
				if (!empty($conf->global->MAIN_ADD_SALE_REP_SIGNATURE_IN_NOTE))
				{
					// Get first sale rep
					if (is_object($object->thirdparty))
					{
						$salereparray = $object->thirdparty->getSalesRepresentatives($user);
						$salerepobj = new User($this->db);
						$salerepobj->fetch($salereparray[0]['id']);
						if (!empty($salerepobj->signature)) $notetoshow = dol_concatdesc($notetoshow, $salerepobj->signature);
					}
				}
				if ($notetoshow)
				{
					$tab_top -= 2;

					$substitutionarray = pdf_getSubstitutionArray($outputlangs, null, $object);
					complete_substitutions_array($substitutionarray, $outputlangs, $object);
					$notetoshow = make_substitutions($notetoshow, $substitutionarray, $outputlangs);
					$notetoshow = convertBackOfficeMediasLinksToPublicLinks($notetoshow);
					$pdf->SetFont('', '', $default_font_size - 1);
$pdf->writeHTMLCell (190,3, $this->posxdesc-1, $tab_top-5.5,"Note Public:",0,1);

					$pdf->SetFont('', '', $default_font_size - 1);
					$pdf->writeHTMLCell(190, 3, $this->posxdesc - 1, $tab_top - 1, dol_htmlentitiesbr($notetoshow), 0, 1);
					$nexY = $pdf->GetY();
					$height_note = $nexY - $tab_top;

					// Rect takes a length in 3rd parameter
					$pdf->SetDrawColor(192, 192, 192);
					$pdf->Rect($this->marge_gauche, $tab_top - 1, $this->page_largeur - $this->marge_gauche - $this->marge_droite, $height_note + 1);

					$tab_top = $nexY + 6;
				}

				$iniY = $tab_top + 7;
				$curY = $tab_top + 7;
				$nexY = $tab_top + 7;

				// Loop on each lines
$sql5=  "SELECT  CASE(pex.taxable) WHEN '1' THEN 'Y' WHEN '0' THEN 'N' END AS tax FROM ".MAIN_DB_PREFIX."facturedet AS f ";
                    $sql5.= " LEFT JOIN ".MAIN_DB_PREFIX."product AS p ON f.fk_product = p.rowid ";
                    $sql5.= " LEFT JOIN ".MAIN_DB_PREFIX."product_extrafields AS pex ON  pex.fk_object = p.rowid ";
                    $sql5.= " WHERE  f.fk_facture =  ".$object->id;
                    $resql5 = $this->db->query($sql5);
                $i = 0;
                $num5 = $this->db->num_rows($resql5);

                $m=0;
                foreach($resql5 as $values5)
                {
                    foreach($values5 as $value5)
                    {
                        $hid[$m][].=	$value5;
                    }
                    $m=$m+1;
                }

				for ($i = 0; $i < $nblines; $i++)
				{
					if ($object->lines[$i]->fk_product)
					{
						require_once (DOL_DOCUMENT_ROOT."/product/class/product.class.php");
						$product = new Product($this->db);
						$product->fetch($object->lines[$i]->fk_product);
						$pdf->SetXY(117,$curY);
						$extrafields_product = new ExtraFields($this->db);
						$extralabels_product = $extrafields_product->fetch_name_optionals_label($product->table_element);

						$product->fetch_optionals($product->rowid, $extralabels_product);
					}
					$object->lines[$i]->fetch_optionals($object->lines[$i]->rowid,$extralabelsline);
					$curY = $nexY;
					$pdf->SetFont('', '', $default_font_size - 1); // Into loop to work with multipage
					$pdf->SetTextColor(0, 0, 0);

					// Define size of image if we need it
					$imglinesize = array();
					if (!empty($realpatharray[$i])) $imglinesize = pdf_getSizeForImage($realpatharray[$i]);

					$pdf->setTopMargin($tab_top_newpage);
					$pdf->setPageOrientation('', 1, $heightforfooter + $heightforfreetext + $heightforinfotot); // The only function to edit the bottom margin of current page to set it.
					$pageposbefore = $pdf->getPage();

					$showpricebeforepagebreak = 1;
					$posYAfterImage = 0;
					
					
					$posYAfterDescription = 0;

					// We start with Photo of product line
					if (isset($imglinesize['width']) && isset($imglinesize['height']) && ($curY + $imglinesize['height']) > ($this->page_hauteur - ($heightforfooter + $heightforfreetext + $heightforinfotot)))	// If photo too high, we moved completely on new page
					{
						$pdf->AddPage('', '', true);
						if (!empty($tplidx)) $pdf->useTemplate($tplidx);
						if (empty($conf->global->MAIN_PDF_DONOTREPEAT_HEAD)) $this->_pagehead($pdf, $object, 0, $outputlangs);
						$pdf->setPage($pageposbefore + 1);

						$curY = $tab_top_newpage;

						// Allows data in the first page if description is long enough to break in multiples pages
						if(!empty($conf->global->MAIN_PDF_DATA_ON_FIRST_PAGE))
							$showpricebeforepagebreak = 1;
						else
							$showpricebeforepagebreak = 0;
					}

					if (isset($imglinesize['width']) && isset($imglinesize['height']))
					{
						$curX = $this->posxpicture - 1;
						$pdf->Image($realpatharray[$i], $curX + (($this->posxtva - $this->posxpicture - $imglinesize['width']) / 2), $curY, $imglinesize['width'], $imglinesize['height'], '', '', '', 2, 300); // Use 300 dpi
						// $pdf->Image does not increase value return by getY, so we save it manually
						$posYAfterImage = $curY + $imglinesize['height'];
					}

					// Description of product line
					$curX = $this->posxdesc - 1;

          $pdf->startTransaction();
          $pdf->writeHTMLCell($this->posxcomment - $this->posxpiece - 0.8, 4, $curX, $curY, $i + 1, 0, 1); // Accountancy piece
					pdf_writelinedesc($pdf, $object, $i, $outputlangs, $this->posxpicture - $curX - $progress_width - 12, 3, $curX + 12, $curY, $hideref, $hidedesc);
					$pageposafter = $pdf->getPage();
					if ($pageposafter > $pageposbefore)	// There is a pagebreak
					{
						$pdf->rollbackTransaction(true);
						$pageposafter = $pageposbefore;
						//print $pageposafter.'-'.$pageposbefore;exit;
            $pdf->setPageOrientation('', 1, $heightforfooter); // The only function to edit the bottom margin of current page to set it.
            $pdf->writeHTMLCell($this->posxcomment - $this->posxpiece - 0.8, 4, $curX, $curY, $i + 1, 0, 1); // Accountancy piece
						pdf_writelinedesc($pdf, $object, $i, $outputlangs, $this->posxpicture - $curX - $progress_width - 12, 3, $curX + 12, $curY, $hideref, $hidedesc);
						$pageposafter = $pdf->getPage();
						$posyafter = $pdf->GetY();
						//var_dump($posyafter); var_dump(($this->page_hauteur - ($heightforfooter+$heightforfreetext+$heightforinfotot))); exit;
						if ($posyafter > ($this->page_hauteur - ($heightforfooter + $heightforfreetext + $heightforinfotot)))	// There is no space left for total+free text
						{
							if ($i == ($nblines - 1))	// No more lines, and no space left to show total, so we create a new page
							{
								$pdf->AddPage('', '', true);
								if (!empty($tplidx)) $pdf->useTemplate($tplidx);
								if (empty($conf->global->MAIN_PDF_DONOTREPEAT_HEAD)) $this->_pagehead($pdf, $object, 0, $outputlangs);
								$pdf->setPage($pageposafter + 1);
							}
						}
						else
						{
							// We found a page break

							// Allows data in the first page if description is long enough to break in multiples pages
							if(!empty($conf->global->MAIN_PDF_DATA_ON_FIRST_PAGE))
								$showpricebeforepagebreak = 1;
							else
								$showpricebeforepagebreak = 0;
						}
					}
					else	// No pagebreak
					{
						$pdf->commitTransaction();
					}
					$posYAfterDescription = $pdf->GetY();

					$nexY = $pdf->GetY();
					$pageposafter = $pdf->getPage();
					$pdf->setPage($pageposbefore);
					$pdf->setTopMargin($this->marge_haute);
					$pdf->setPageOrientation('', 1, 0); // The only function to edit the bottom margin of current page to set it.

					// We suppose that a too long description or photo were moved completely on next page
					if ($pageposafter > $pageposbefore && empty($showpricebeforepagebreak)) {
						$pdf->setPage($pageposafter); $curY = $tab_top_newpage;
					}

					$pdf->SetFont('', '', $default_font_size - 1); // We reposition the default font
	//				global $extrafields;

//$test= $extrafields->showOutputField('test',$object->lines[$i]->array_options ["options_test"]);
//$pdf->SetXY(50,$curY+4);
//$pdf->MultiCell(0, 3, 'rey'.$test, 0, 'L', 0);

					// VAT Rate
					if (empty($conf->global->MAIN_GENERATE_DOCUMENTS_WITHOUT_VAT) && empty($conf->global->MAIN_GENERATE_DOCUMENTS_WITHOUT_VAT_COLUMN))
					{
						$vat_rate = pdf_getlinevatrate($object, $i, $outputlangs, $hidedetails);
						$pdf->SetXY($this->posxtva - 1, $curY);
						$pdf->MultiCell($this->posxup - $this->posxtva + 1, 3, $hid[$i][0], 0, 'C', 0);
					//	$pdf->MultiCell($this->posxup - $this->posxtva + 4, 3, $vat_rate, 0, 'R');
					}

					// Unit price before discount
					$up_excl_tax = pdf_getlineupexcltax($object, $i, $outputlangs, $hidedetails);
					$pdf->SetXY($this->posxup, $curY);
					$pdf->MultiCell($this->posxqty - $this->posxup - 0.8, 3, $up_excl_tax, 0, 'C', 0);

					// Quantity
					$qty = pdf_getlineqty($object, $i, $outputlangs, $hidedetails);
					$pdf->SetXY($this->posxqty, $curY);
					$pdf->MultiCell($this->posxunit - $this->posxqty - 0.8, 4, $qty, 0, 'C'); // Enough for 6 chars

					// Unit
					if (!empty($conf->global->PRODUCT_USE_UNITS))
					{
						$unit = pdf_getlineunit($object, $i, $outputlangs, $hidedetails, $hookmanager);
						$pdf->SetXY($this->posxunit, $curY);
						$pdf->MultiCell($this->posxdiscount - $this->posxunit - 0.8, 4, $unit, 0, 'L');
					}

					// Discount on line
					if ($object->lines[$i]->remise_percent)
					{
                        $pdf->SetXY($this->posxdiscount - 2, $curY);
					  $remise_percent = pdf_getlineremisepercent($object, $i, $outputlangs, $hidedetails);
					    $pdf->MultiCell($this->posxprogress - $this->posxdiscount + 2, 3, $remise_percent, 0, 'C');
					}

					// Situation progress
					if ($this->situationinvoice)
					{
					    $progress = pdf_getlineprogress($object, $i, $outputlangs, $hidedetails);
					    $pdf->SetXY($this->posxprogress, $curY);
				        $pdf->MultiCell($this->postotalht - $this->posxprogress + 1, 3, $progress, 0, 'R');
					}

					// Total HT line
					$total_excl_tax = pdf_getlinetotalexcltax($object, $i, $outputlangs, $hidedetails);
					$pdf->SetXY($this->postotalht, $curY);
					$pdf->MultiCell($this->page_largeur - $this->marge_droite - $this->postotalht, 3, $total_excl_tax, 0, 'R', 0);


					$sign = 1;
					if (isset($object->type) && $object->type == 2 && !empty($conf->global->INVOICE_POSITIVE_CREDIT_NOTE)) $sign = -1;
					// Collection of totals by value of VAT in $this->tva["taux"]=total_tva
					$prev_progress = $object->lines[$i]->get_prev_progress($object->id);
					if ($prev_progress > 0 && !empty($object->lines[$i]->situation_percent)) // Compute progress from previous situation
					{
						if ($conf->multicurrency->enabled && $object->multicurrency_tx != 1) $tvaligne = $sign * $object->lines[$i]->multicurrency_total_tva * ($object->lines[$i]->situation_percent - $prev_progress) / $object->lines[$i]->situation_percent;
						else $tvaligne = $sign * $object->lines[$i]->total_tva * ($object->lines[$i]->situation_percent - $prev_progress) / $object->lines[$i]->situation_percent;
					} else {
						if ($conf->multicurrency->enabled && $object->multicurrency_tx != 1) $tvaligne = $sign * $object->lines[$i]->multicurrency_total_tva;
						else $tvaligne = $sign * $object->lines[$i]->total_tva;
					}

					$localtax1ligne = $object->lines[$i]->total_localtax1;
					$localtax2ligne = $object->lines[$i]->total_localtax2;
					$localtax1_rate = $object->lines[$i]->localtax1_tx;
					$localtax2_rate = $object->lines[$i]->localtax2_tx;
					$localtax1_type = $object->lines[$i]->localtax1_type;
					$localtax2_type = $object->lines[$i]->localtax2_type;

					if ($object->remise_percent) $tvaligne -= ($tvaligne * $object->remise_percent) / 100;
					if ($object->remise_percent) $localtax1ligne -= ($localtax1ligne * $object->remise_percent) / 100;
					if ($object->remise_percent) $localtax2ligne -= ($localtax2ligne * $object->remise_percent) / 100;

					$vatrate = (string) $object->lines[$i]->tva_tx;

					// Retrieve type from database for backward compatibility with old records
					if ((!isset($localtax1_type) || $localtax1_type == '' || !isset($localtax2_type) || $localtax2_type == '') // if tax type not defined
					&& (!empty($localtax1_rate) || !empty($localtax2_rate))) // and there is local tax
					{
						$localtaxtmp_array = getLocalTaxesFromRate($vatrate, 0, $object->thirdparty, $mysoc);
						$localtax1_type = $localtaxtmp_array[0];
						$localtax2_type = $localtaxtmp_array[2];
					}

				    // retrieve global local tax
					if ($localtax1_type && $localtax1ligne != 0)
						$this->localtax1[$localtax1_type][$localtax1_rate] += $localtax1ligne;
					if ($localtax2_type && $localtax2ligne != 0)
						$this->localtax2[$localtax2_type][$localtax2_rate] += $localtax2ligne;

					if (($object->lines[$i]->info_bits & 0x01) == 0x01) $vatrate .= '*';
					if (!isset($this->tva[$vatrate])) 				$this->tva[$vatrate] = 0;
					$this->tva[$vatrate] += $tvaligne;

					if ($posYAfterImage > $posYAfterDescription) $nexY = $posYAfterImage;

					// Add line
					if (!empty($conf->global->MAIN_PDF_DASH_BETWEEN_LINES) && $i < ($nblines - 1))
					{
						$pdf->setPage($pageposafter);
						$pdf->SetLineStyle(array('dash'=>'1,1', 'color'=>array(80, 80, 80)));
						//$pdf->SetDrawColor(190,190,200);
						$pdf->line($this->marge_gauche, $nexY + 1, $this->page_largeur - $this->marge_droite, $nexY + 1);
						$pdf->SetLineStyle(array('dash'=>0));
					}

					$nexY += 2; // Add space between lines

					// Detect if some page were added automatically and output _tableau for past pages
					while ($pagenb < $pageposafter)
					{
						$pdf->setPage($pagenb);
						if ($pagenb == 1)
						{
							$this->_tableau($pdf, $tab_top, $this->page_hauteur - $tab_top - $heightforfooter, 0, $outputlangs, 0, 1, $object->multicurrency_code);
						}
						else
						{
							$this->_tableau($pdf, $tab_top_newpage, $this->page_hauteur - $tab_top_newpage - $heightforfooter, 0, $outputlangs, 1, 1, $object->multicurrency_code);
						}
						$this->_pagefoot($pdf, $object, $outputlangs, 1);
						$pagenb++;
						$pdf->setPage($pagenb);
						$pdf->setPageOrientation('', 1, 0); // The only function to edit the bottom margin of current page to set it.
						if (empty($conf->global->MAIN_PDF_DONOTREPEAT_HEAD)) $this->_pagehead($pdf, $object, 0, $outputlangs);
					}
					if (isset($object->lines[$i + 1]->pagebreak) && $object->lines[$i + 1]->pagebreak)
					{
						if ($pagenb == 1)
						{
							$this->_tableau($pdf, $tab_top, $this->page_hauteur - $tab_top - $heightforfooter, 0, $outputlangs, 0, 1, $object->multicurrency_code);
						}
						else
						{
							$this->_tableau($pdf, $tab_top_newpage, $this->page_hauteur - $tab_top_newpage - $heightforfooter, 0, $outputlangs, 1, 1, $object->multicurrency_code);
						}
						$this->_pagefoot($pdf, $object, $outputlangs, 1);
						// New page
						$pdf->AddPage();
						if (!empty($tplidx)) $pdf->useTemplate($tplidx);
						$pagenb++;
						if (empty($conf->global->MAIN_PDF_DONOTREPEAT_HEAD)) $this->_pagehead($pdf, $object, 0, $outputlangs);
					}
				}

				// Show square
				if ($pagenb == 1)
				{
					$this->_tableau($pdf, $tab_top, $this->page_hauteur - $tab_top - $heightforinfotot - $heightforfreetext - $heightforfooter, 0, $outputlangs, 0, 0, $object->multicurrency_code);
					$bottomlasttab = $this->page_hauteur - $heightforinfotot - $heightforfreetext - $heightforfooter + 1;
				}
				else
				{
					$this->_tableau($pdf, $tab_top_newpage, $this->page_hauteur - $tab_top_newpage - $heightforinfotot - $heightforfreetext - $heightforfooter, 0, $outputlangs, 1, 0, $object->multicurrency_code);
					$bottomlasttab = $this->page_hauteur - $heightforinfotot - $heightforfreetext - $heightforfooter + 1;
				}

				// Display info area
				$posy = $this->_tableau_info($pdf, $object, $bottomlasttab, $outputlangs);

				// Display total area
				$posy = $this->_tableau_tot($pdf, $object, $deja_regle, $bottomlasttab, $outputlangs);

				// Display Payments area
				if (($deja_regle || $amount_credit_notes_included || $amount_deposits_included) && empty($conf->global->INVOICE_NO_PAYMENT_DETAILS))
				{
					$posy = $this->_tableau_versements($pdf, $object, $posy, $outputlangs, $heightforfooter);
				}

				// Pagefoot
				$this->_pagefoot($pdf, $object, $outputlangs);
				if (method_exists($pdf, 'AliasNbPages')) $pdf->AliasNbPages();

				$pdf->Close();

				$pdf->Output($file, 'F');

				// Add pdfgeneration hook
				$hookmanager->initHooks(array('pdfgeneration'));
				$parameters = array('file'=>$file, 'object'=>$object, 'outputlangs'=>$outputlangs);
				global $action;
				$reshook = $hookmanager->executeHooks('afterPDFCreation', $parameters, $this, $action); // Note that $action and $object may have been modified by some hooks
				if ($reshook < 0)
				{
				    $this->error = $hookmanager->error;
				    $this->errors = $hookmanager->errors;
				}

				if (!empty($conf->global->MAIN_UMASK))
				@chmod($file, octdec($conf->global->MAIN_UMASK));

				$this->result = array('fullpath'=>$file);

				return 1; // No error
			}
			else
			{
				$this->error = $langs->transnoentities("ErrorCanNotCreateDir", $dir);
				return 0;
			}
		}
		else
		{
			$this->error = $langs->transnoentities("ErrorConstantNotDefined", "FAC_OUTPUTDIR");
			return 0;
		}
	}


	// phpcs:disable PEAR.NamingConventions.ValidFunctionName.ScopeNotCamelCaps
	// phpcs:disable PEAR.NamingConventions.ValidFunctionName.PublicUnderscore
	/**
	 *  Show payments table
	 *
     *  @param	PDF			$pdf            Object PDF
     *  @param  Object		$object         Object invoice
     *  @param  int			$posy           Position y in PDF
     *  @param  Translate	$outputlangs    Object langs for output
     *  @param  int			$heightforfooter height for footer
     *  @return int             			<0 if KO, >0 if OK
	 */
	protected function _tableau_versements(&$pdf, $object, $posy, $outputlangs, $heightforfooter = 0)
	{
        // phpcs:enable
		global $conf;

        $sign = 1;
        if ($object->type == 2 && !empty($conf->global->INVOICE_POSITIVE_CREDIT_NOTE)) $sign = -1;

		$current_page = $pdf->getPage();
        $tab3_posx = 120;
		$tab3_top = $posy + 8;
		$tab3_width = 80;
		$tab3_height = 4;
		if ($this->page_largeur < 210) // To work with US executive format
		{
			$tab3_posx -= 20;
		}

		$default_font_size = pdf_getPDFFontSize($outputlangs);

		$this->_tableau_versements_header($pdf, $object, $outputlangs, $default_font_size, $tab3_posx, $tab3_top, $tab3_width, $tab3_height);

		$y = 0;

		$pdf->SetFont('', '', $default_font_size - 4);


		// Loop on each discount available (deposits and credit notes and excess of payment included)
		$sql = "SELECT re.rowid, re.amount_ht, re.multicurrency_amount_ht, re.amount_tva, re.multicurrency_amount_tva,  re.amount_ttc, re.multicurrency_amount_ttc,";
		$sql .= " re.description, re.fk_facture_source,";
		$sql .= " f.type, f.datef";
		$sql .= " FROM ".MAIN_DB_PREFIX."societe_remise_except as re, ".MAIN_DB_PREFIX."facture as f";
		$sql .= " WHERE re.fk_facture_source = f.rowid AND re.fk_facture = ".$object->id;
		$resql = $this->db->query($sql);
		if ($resql)
		{
			$num = $this->db->num_rows($resql);
			$i = 0;
			$invoice = new Facture($this->db);
			while ($i < $num)
			{
				$y += 3;
				if ($tab3_top + $y >= ($this->page_hauteur - $heightforfooter))
				{
					$y = 0;
					$current_page++;
					$pdf->AddPage('', '', true);
					if (!empty($tplidx)) $pdf->useTemplate($tplidx);
					if (empty($conf->global->MAIN_PDF_DONOTREPEAT_HEAD)) $this->_pagehead($pdf, $object, 0, $outputlangs);
					$pdf->setPage($current_page);
					$this->_tableau_versements_header($pdf, $object, $outputlangs, $default_font_size, $tab3_posx, $tab3_top + $y - 3, $tab3_width, $tab3_height);
				}

				$obj = $this->db->fetch_object($resql);

				if ($obj->type == 2) $text = $outputlangs->transnoentities("CreditNote");
				elseif ($obj->type == 3) $text = $outputlangs->transnoentities("Deposit");
				elseif ($obj->type == 0) $text = $outputlangs->transnoentities("ExcessReceived");
				else $text = $outputlangs->transnoentities("UnknownType");

				$invoice->fetch($obj->fk_facture_source);

				$pdf->SetXY($tab3_posx, $tab3_top + $y);
				$pdf->MultiCell(20, 3, dol_print_date($this->db->jdate($obj->datef), 'day', false, $outputlangs, true), 0, 'L', 0);
				$pdf->SetXY($tab3_posx + 21, $tab3_top + $y);
				$pdf->MultiCell(20, 3, price(($conf->multicurrency->enabled && $object->multicurrency_tx != 1) ? $obj->multicurrency_amount_ttc : $obj->amount_ttc, 0, $outputlangs), 0, 'L', 0);
				$pdf->SetXY($tab3_posx + 36, $tab3_top + $y);

				$pdf->SetXY($tab3_posx + 58, $tab3_top + $y);
				$pdf->MultiCell(20, 3, $invoice->ref, 0, 'L', 0);

				$pdf->line($tab3_posx, $tab3_top + $y + 3, $tab3_posx + $tab3_width, $tab3_top + $y + 3);

				$i++;
			}
		}
		else
		{
			$this->error = $this->db->lasterror();
			return -1;
		}

		// Loop on each payment
		// TODO Call getListOfPaymentsgetListOfPayments instead of hard coded sql
		$sql = "SELECT p.datep as date, p.fk_paiement, p.num_paiement as num, pf.amount as amount, pf.multicurrency_amount,";
		$sql .= " cp.code";
		$sql .= " FROM ".MAIN_DB_PREFIX."paiement_facture as pf, ".MAIN_DB_PREFIX."paiement as p";
		$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."c_paiement as cp ON p.fk_paiement = cp.id";
		$sql .= " WHERE pf.fk_paiement = p.rowid AND pf.fk_facture = ".$object->id;
		//$sql.= " WHERE pf.fk_paiement = p.rowid AND pf.fk_facture = 1";
		$sql .= " ORDER BY p.datep";

		$resql = $this->db->query($sql);
		if ($resql)
		{
			$num = $this->db->num_rows($resql);
			$i = 0;
			while ($i < $num) {
				$y += 3;
				if ($tab3_top + $y >= ($this->page_hauteur - $heightforfooter))
				{
					$y = 0;
					$current_page++;
					$pdf->AddPage('', '', true);
					if (!empty($tplidx)) $pdf->useTemplate($tplidx);
					if (empty($conf->global->MAIN_PDF_DONOTREPEAT_HEAD)) $this->_pagehead($pdf, $object, 0, $outputlangs);
					$pdf->setPage($current_page);
					$this->_tableau_versements_header($pdf, $object, $outputlangs, $default_font_size, $tab3_posx, $tab3_top + $y - 3, $tab3_width, $tab3_height);
				}

				$row = $this->db->fetch_object($resql);

				$pdf->SetXY($tab3_posx, $tab3_top + $y);
				$pdf->MultiCell(20, 3, dol_print_date($this->db->jdate($row->date), 'day', false, $outputlangs, true), 0, 'L', 0);
				$pdf->SetXY($tab3_posx + 21, $tab3_top + $y);
				$pdf->MultiCell(20, 3, price($sign * (($conf->multicurrency->enabled && $object->multicurrency_tx != 1) ? $row->multicurrency_amount : $row->amount), 0, $outputlangs), 0, 'L', 0);
				$pdf->SetXY($tab3_posx + 40, $tab3_top + $y);

				$pdf->MultiCell(20, 3, $oper, 0, 'L', 0);
				$pdf->SetXY($tab3_posx + 58, $tab3_top + $y);
				$pdf->MultiCell(30, 3, $row->num, 0, 'L', 0);

				$pdf->line($tab3_posx, $tab3_top + $y + 3, $tab3_posx + $tab3_width, $tab3_top + $y + 3);

				$i++;
			}

			return $tab3_top + $y + 3;
		}
		else
		{
			$this->error = $this->db->lasterror();
			return -1;
		}
	}

	// phpcs:disable PEAR.NamingConventions.ValidFunctionName.ScopeNotCamelCaps
	// phpcs:disable PEAR.NamingConventions.ValidFunctionName.PublicUnderscore
	/**
	 * Function _tableau_versements_header
	 *
	 * @param TCPDF 		$pdf				Object PDF
	 * @param Facture		$object				Object invoice
	 * @param Translate		$outputlangs		Object langs for output
	 * @param int			$default_font_size	Font size
	 * @param int			$tab3_posx			pos x
	 * @param int 			$tab3_top			pos y
	 * @param int 			$tab3_width			width
	 * @param int 			$tab3_height		height
	 * @return void
	 */
	protected function _tableau_versements_header($pdf, $object, $outputlangs, $default_font_size, $tab3_posx, $tab3_top, $tab3_width, $tab3_height)
	{
	    // phpcs:enable
	    $title = $outputlangs->transnoentities("PaymentsAlreadyDone");
		if ($object->type == 2) $title = $outputlangs->transnoentities("PaymentsBackAlreadyDone");

		$pdf->SetFont('', '', $default_font_size - 3);
		$pdf->SetXY($tab3_posx, $tab3_top - 4);
		$pdf->MultiCell(60, 3, 'Deposits already done', 0, 'L', 0);

		$pdf->line($tab3_posx, $tab3_top, $tab3_posx + $tab3_width, $tab3_top);

		$pdf->SetFont('', '', $default_font_size - 4);
		$pdf->SetXY($tab3_posx, $tab3_top);
		$pdf->MultiCell(20, 3, $outputlangs->transnoentities("Payment"), 0, 'L', 0);
		$pdf->SetXY($tab3_posx + 21, $tab3_top);
		$pdf->MultiCell(20, 3, $outputlangs->transnoentities("Amount"), 0, 'L', 0);
		$pdf->SetXY($tab3_posx + 36, $tab3_top);
		$pdf->MultiCell(20, 3, $outputlangs->transnoentities("Credit Note"), 0, 'L', 0);
		$pdf->SetXY($tab3_posx + 68, $tab3_top);
		$pdf->MultiCell(20, 3, $outputlangs->transnoentities("Type"), 0, 'L', 0);
		$pdf->SetXY($tab3_posx + 58, $tab3_top);
		$pdf->MultiCell(20, 3, $outputlangs->transnoentities("Num"), 0, 'L', 0);


		$pdf->line($tab3_posx, $tab3_top - 1 + $tab3_height, $tab3_posx + $tab3_width, $tab3_top - 1 + $tab3_height);
	}

	// phpcs:disable PEAR.NamingConventions.ValidFunctionName.ScopeNotCamelCaps
	// phpcs:disable PEAR.NamingConventions.ValidFunctionName.PublicUnderscore
	/**
	 *   Show miscellaneous information (payment mode, payment term, ...)
	 *
	 *   @param		PDF			$pdf     		Object PDF
	 *   @param		Object		$object			Object to show
	 *   @param		int			$posy			Y
	 *   @param		Translate	$outputlangs	Langs object
	 *   @return	void
	 */
	protected function _tableau_info(&$pdf, $object, $posy, $outputlangs)
	{
        // phpcs:enable
		global $conf;

		$default_font_size = pdf_getPDFFontSize($outputlangs);

		$pdf->SetFont('', '', $default_font_size - 1);

		// If France, show VAT mention if not applicable
		if ($this->emetteur->country_code == 'FR' && $this->franchise == 1)
		{
			$pdf->SetFont('', 'B', $default_font_size - 2);
			$pdf->SetXY($this->marge_gauche, $posy);
			$pdf->MultiCell(100, 3, $outputlangs->transnoentities("VATIsNotUsedForInvoice"), 0, 'L', 0);

			$posy = $pdf->GetY() + 4;
		}

		$posxval = 52;

		// Show payments conditions
		if ($object->type != 2 && ($object->cond_reglement_code || $object->cond_reglement))
		{
			$pdf->SetFont('', 'B', $default_font_size - 2);
			$pdf->SetXY($this->marge_gauche, $posy);
			$titre = $outputlangs->transnoentities("PaymentConditions").':';
			$pdf->MultiCell(43, 4, $titre, 0, 'L');

			$pdf->SetFont('', '', $default_font_size - 2);
			$pdf->SetXY($posxval, $posy);
			$lib_condition_paiement = $outputlangs->transnoentities("PaymentCondition".$object->cond_reglement_code) != ('PaymentCondition'.$object->cond_reglement_code) ? $outputlangs->transnoentities("PaymentCondition".$object->cond_reglement_code) : $outputlangs->convToOutputCharset($object->cond_reglement_doc);
			$lib_condition_paiement = str_replace('\n', "\n", $lib_condition_paiement);
			$pdf->MultiCell(67, 4, $lib_condition_paiement, 0, 'L');

			$posy = $pdf->GetY() + 3;
		}

$pdf->SetFont('', '',5);
$pdf->SetXY($posx+10  , $posy+19);
 $pdf->MultiCell(80, 3, "
Please review this metal material list thoroughly. BRS Roofing Supply cannot be held responsible for incomplete or inaccurate orders. BRS Roofing Supply agrees to provide all products in the listed quantities summarized on this list. All transactions are final and cannot be edited, updated, or canceled once the order has been finalized. Partial or full payment of the order represents an agreement to receive the full order by the listed ship or pick-up date.", 0, 'L', 0);
//$pdf->SetFont('', '',6);
//$pdf->SetXY($posx+8 , $posy+31);
//$pdf->MultiCell(200, 1, "
//For returns is very important to bring your proof of Purchase BRS will not accept returns over 30 days of the purchase day.We will apply 15% for restocking fee. All material must be in perfect conditions to be accepted.  1.5% SERVICE CHARGE PER MONTH ON ALL AMOUNTS OVER 30 DAYS. ALL CLAIMS AND RETURNED GOODS MUST BE ACCOMPANIED BY THIS YARD ORDER. A RESTOCKING FEE WILL BE IMPOSED ON ALL RETURNS.", 0, 'C', 0);			

            $pdf->SetFont('', '', $default_font_size + 4);
			//$pdf->SetTextColor(0, 0, 60);			
			//$pdf->SetXY($posx-0 , $posy+102);
			//$titre = $outputlangs->transnoentities("Thank you for your business!");
			//$pdf->MultiCell(83, 4, "Thank you for your business!", 0, 'C');
			
			$pdf->SetFont('', '', $default_font_size -4 );
			$pdf->SetXY($posx+10 , $posy +40);
			//$lib_mode_reg ='1.5% SERVICE CHARGE PER MONTH ON ALL AMOUNTS OVER 30 DAYS. ALL CLAIMS AND RETURNED GOODS MUST BE ACCOMPANIED BY THIS YARD ORDER. A RESTOCKING FEE WILL BE IMPOSED ON ALL RETURNS.';
			//$pdf->MultiCell(205, 6, $lib_mode_reg , 0, 'L');

		if ($object->type != 2)
		{
			// Check a payment mode is defined
			if (empty($object->mode_reglement_code)
			&& empty($conf->global->FACTURE_CHQ_NUMBER)
			&& empty($conf->global->FACTURE_RIB_NUMBER))
			{
				$this->error = $outputlangs->transnoentities("ErrorNoPaiementModeConfigured");
			}
			// Avoid having any valid PDF with setup that is not complete
			elseif (($object->mode_reglement_code == 'CHQ' && empty($conf->global->FACTURE_CHQ_NUMBER) && empty($object->fk_account) && empty($object->fk_bank))
				|| ($object->mode_reglement_code == 'VIR' && empty($conf->global->FACTURE_RIB_NUMBER) && empty($object->fk_account) && empty($object->fk_bank)))
			{
				$outputlangs->load("errors");

				$pdf->SetXY($this->marge_gauche, $posy);
				$pdf->SetTextColor(200, 0, 0);
				$pdf->SetFont('', 'B', $default_font_size - 2);
				$this->error = $outputlangs->transnoentities("ErrorPaymentModeDefinedToWithoutSetup", $object->mode_reglement_code);
				$pdf->MultiCell(80, 3, $this->error, 0, 'L', 0);
				$pdf->SetTextColor(0, 0, 0);

				$posy = $pdf->GetY() + 1;
			}

			// Show payment mode
			if ($object->mode_reglement_code
			&& $object->mode_reglement_code != 'CHQ'
			&& $object->mode_reglement_code != 'VIR')
			{
				$pdf->SetFont('', 'B', $default_font_size - 2);
				$pdf->SetXY($this->marge_gauche, $posy);
				$titre = $outputlangs->transnoentities("PaymentMode").':';
				$pdf->MultiCell(80, 5, $titre, 0, 'L');

				$pdf->SetFont('', '', $default_font_size - 2);
				$pdf->SetXY($posxval, $posy);
				$lib_mode_reg = $outputlangs->transnoentities("PaymentType".$object->mode_reglement_code) != ('PaymentType'.$object->mode_reglement_code) ? $outputlangs->transnoentities("PaymentType".$object->mode_reglement_code) : $outputlangs->convToOutputCharset($object->mode_reglement);
				$pdf->MultiCell(80, 5, $lib_mode_reg, 0, 'L');

				$posy = $pdf->GetY() + 2;
				

												$remx = new client($this->db);
                $result = $remx->fetch($object->thirdparty->id);
                $amount_discount = $remx->getAvailableDiscounts();

				$pdf->MultiCell(80, 3, "Credit Available:                            ".price($amount_discount), 0, 'L', 0);

}
$pdf->writeHTMLCell(100, 3, $this->posxdesc, $tab_top+269, dol_print_date(date('Y-m-d H:i:s'),false,$outputlangs), '', 'L');	
						$pdf->SetFont('', 'B', $default_font_size + 5);
			// $pdf->SetTextColor(0, 0, 60);			
			// $pdf->SetXY($posx+65 , $posy+35);
			// $titre = $outputlangs->transnoentities("Thank you for your business!");
			// $pdf->MultiCell(83, 4, $titre, 0, 'C');
			
			// $posy = $pdf->GetY() + 2;
			// Show payment mode CHQ
			if (empty($object->mode_reglement_code) || $object->mode_reglement_code == 'CHQ')
			{
			    // If unregulated or forced payment mode to CHQ
				if (!empty($conf->global->FACTURE_CHQ_NUMBER))
				{
					$diffsizetitle = (empty($conf->global->PDF_DIFFSIZE_TITLE) ? 3 : $conf->global->PDF_DIFFSIZE_TITLE);

					if ($conf->global->FACTURE_CHQ_NUMBER > 0)
					{
						$account = new Account($this->db);
						$account->fetch($conf->global->FACTURE_CHQ_NUMBER);

						$pdf->SetXY($this->marge_gauche, $posy);
						$pdf->SetFont('', 'B', $default_font_size - $diffsizetitle);
						$pdf->MultiCell(100, 3, $outputlangs->transnoentities('PaymentByChequeOrderedTo', $account->proprio), 0, 'L', 0);
						$posy = $pdf->GetY() + 1;

			            if (empty($conf->global->MAIN_PDF_HIDE_CHQ_ADDRESS))
			            {
							$pdf->SetXY($this->marge_gauche, $posy);
							$pdf->SetFont('', '', $default_font_size - $diffsizetitle);
							$pdf->MultiCell(100, 3, $outputlangs->convToOutputCharset($account->owner_address), 0, 'L', 0);
							$posy = $pdf->GetY() + 2;
			            }
					}
					if ($conf->global->FACTURE_CHQ_NUMBER == -1)
					{
						$pdf->SetXY($this->marge_gauche, $posy);
						$pdf->SetFont('', 'B', $default_font_size - $diffsizetitle);
						$pdf->MultiCell(100, 3, $outputlangs->transnoentities('PaymentByChequeOrderedTo', $this->emetteur->name), 0, 'L', 0);
						$posy = $pdf->GetY() + 1;

			            if (empty($conf->global->MAIN_PDF_HIDE_CHQ_ADDRESS))
			            {
							$pdf->SetXY($this->marge_gauche, $posy);
							$pdf->SetFont('', '', $default_font_size - $diffsizetitle);
							$pdf->MultiCell(100, 3, $outputlangs->convToOutputCharset($this->emetteur->getFullAddress()), 0, 'L', 0);
							$posy = $pdf->GetY() + 2;
			            }
					}
				}
//prueba de texto libre
			/*$pdf->SetFont('', '', $default_font_size + 4);
			$pdf->SetTextColor(0, 0, 0);			
			$pdf->SetXY($posx+70 , $posy+40);
			$titre = $outputlangs->transnoentities("Thank you for your business!");
			$pdf->MultiCell(83, 4, $titre, 0, 'C');
			
			$pdf->SetFont('', '', $default_font_size -3 );
			//$pdf->SetXY($posxval-42 , $posy + 27);
			$lib_mode_reg ='1.5% SERVICE CHARGE PER MONTH ON ALL AMOUNTS OVER 30 DAYS. ALL CLAIMS AND RETURNED GOODS MUST BE ACCOMPANIED BY THIS YARD ORDER. 
			A RESTOCKING FEE WILL BE IMPOSED ON ALL RETURNS.';
			$pdf->MultiCell(200, 4, $lib_mode_reg , 0, 'L');*/

			}
			

			// If payment mode not forced or forced to VIR, show payment with BAN
			if (empty($object->mode_reglement_code) || $object->mode_reglement_code == 'VIR')
			{
				if ($object->fk_account > 0 || $object->fk_bank > 0 || !empty($conf->global->FACTURE_RIB_NUMBER)) {
					$bankid = ($object->fk_account <= 0 ? $conf->global->FACTURE_RIB_NUMBER : $object->fk_account);
					if ($object->fk_bank > 0) $bankid = $object->fk_bank; // For backward compatibility when object->fk_account is forced with object->fk_bank
					$account = new Account($this->db);
					$account->fetch($bankid);

					$curx = $this->marge_gauche;
					$cury = $posy;

					$posy = pdf_bank($pdf, $outputlangs, $curx, $cury, $account, 0, $default_font_size);

					//$posy += 2;
				}
//prueba de texto libre
			/*$pdf->SetFont('', '', $default_font_size + 4);
			$pdf->SetTextColor(0, 0, 0);			
			$pdf->SetXY($posx+70 , $posy+12);
			$titre = $outputlangs->transnoentities("Thank you for your business!");
			$pdf->MultiCell(83, 4, $titre, 0, 'C');
			
			$pdf->SetFont('', '', $default_font_size -3 );
$pdf->SetTextColor(0, 0, 0);
			//$pdf->SetXY($posxval-42 , $posy + 27);
			$lib_mode_reg ='1.5% SERVICE CHARGE PER MONTH ON ALL AMOUNTS OVER 30 DAYS. ALL CLAIMS AND RETURNED GOODS MUST BE ACCOMPANIED BY THIS YARD ORDER. 
			A RESTOCKING FEE WILL BE IMPOSED ON ALL RETURNS.';
			$pdf->MultiCell(200, 4, $lib_mode_reg , 0, 'L');*/

			}

		}
			

		return $posy;
	}


	// phpcs:disable PEAR.NamingConventions.ValidFunctionName.ScopeNotCamelCaps
	// phpcs:disable PEAR.NamingConventions.ValidFunctionName.PublicUnderscore
	/**
	 *	Show total to pay
	 *
	 *	@param	PDF			$pdf            Object PDF
	 *	@param  Facture		$object         Object invoice
	 *	@param  int			$deja_regle     Amount already paid (in the currency of invoice)
	 *	@param	int			$posy			Position depart
	 *	@param	Translate	$outputlangs	Objet langs
	 *	@return int							Position pour suite
	 */
	protected function _tableau_tot(&$pdf, $object, $deja_regle, $posy, $outputlangs)
	{
        // phpcs:enable
		global $conf, $mysoc;

        $sign = 1;
        if ($object->type == 2 && !empty($conf->global->INVOICE_POSITIVE_CREDIT_NOTE)) $sign = -1;

        $default_font_size = pdf_getPDFFontSize($outputlangs);

		$tab2_top = $posy;
		$tab2_hl = 4;
		$pdf->SetFont('', '', $default_font_size - 1);

		// Total table
		$col1x = 120; $col2x = 170;
		if ($this->page_largeur < 210) // To work with US executive format
		{
			$col2x -= 20;
		}
		$largcol2 = ($this->page_largeur - $this->marge_droite - $col2x);

		$useborder = 0;
		$index = 0;

		// Total HT
		$pdf->SetFillColor(255, 255, 255);
		$pdf->SetXY($col1x, $tab2_top + 0);
		//$pdf->MultiCell($col2x - $col1x, $tab2_hl, $outputlangs->transnoentities("TotalHT"), 0, 'L', 1);

		$total_ht = (($conf->multicurrency->enabled && isset($object->multicurrency_tx) && $object->multicurrency_tx != 1) ? $object->multicurrency_total_ht : $object->total_ht);
		$pdf->SetXY($col2x, $tab2_top + 0);
		//$pdf->MultiCell($largcol2, $tab2_hl, price($sign * ($total_ht + (!empty($object->remise) ? $object->remise : 0)), 0, $outputlangs), 0, 'R', 1);

	// Total TTC
				$index++;
				$pdf->SetXY($col1x+25, $tab2_top + $tab2_hl * $index);
				$pdf->SetTextColor(0, 0, 0);
				$pdf->SetFillColor(224, 224, 224);
				$pdf->MultiCell($col2x - $col1x, $tab2_hl, "Amount", $useborder, 'L', 1);

				$pdf->SetXY($col2x, $tab2_top + $tab2_hl * $index);
				$pdf->MultiCell($largcol2, $tab2_hl, price($sign * $total_ht, 0, $outputlangs), $useborder, 'R', 1);

		// Show VAT by rates and total
		$pdf->SetFillColor(248, 248, 248);

		$total_ttc = ($conf->multicurrency->enabled && $object->multicurrency_tx != 1) ? $object->multicurrency_total_ttc : $object->total_ttc;

		$this->atleastoneratenotnull = 0;
		if (empty($conf->global->MAIN_GENERATE_DOCUMENTS_WITHOUT_VAT))
		{
			$tvaisnull = ((!empty($this->tva) && count($this->tva) == 1 && isset($this->tva['0.000']) && is_float($this->tva['0.000'])) ? true : false);
			if (!empty($conf->global->MAIN_GENERATE_DOCUMENTS_WITHOUT_VAT_IFNULL) && $tvaisnull)
			{
				// Nothing to do
			}
			else
			{
			    // FIXME amount of vat not supported with multicurrency

				//Local tax 1 before VAT
				//if (! empty($conf->global->FACTURE_LOCAL_TAX1_OPTION) && $conf->global->FACTURE_LOCAL_TAX1_OPTION=='localtax1on')
				//{
				foreach ($this->localtax1 as $localtax_type => $localtax_rate)
				{
					if (in_array((string) $localtax_type, array('1', '3', '5'))) continue;

					foreach ($localtax_rate as $tvakey => $tvaval)
					{
						if ($tvakey != 0)    // On affiche pas taux 0
						{
							//$this->atleastoneratenotnull++;

							$index++;
							$pdf->SetXY($col1x, $tab2_top + $tab2_hl * $index);

							$tvacompl = '';
							if (preg_match('/\*/', $tvakey))
							{
								$tvakey = str_replace('*', '', $tvakey);
								$tvacompl = " (".$outputlangs->transnoentities("NonPercuRecuperable").")";
							}

							$totalvat = $outputlangs->transcountrynoentities("TotalLT1", $mysoc->country_code).' ';
							$totalvat .= vatrate(abs($tvakey), 1).$tvacompl;
							//$pdf->MultiCell($col2x - $col1x, $tab2_hl, $totalvat, 0, 'L', 1);

							$pdf->SetXY($col2x, $tab2_top + $tab2_hl * $index);
							//$pdf->MultiCell($largcol2, $tab2_hl, price($tvaval, 0, $outputlangs), 0, 'R', 1);
						}
					}
				}
	      		//}
				//Local tax 2 before VAT
				//if (! empty($conf->global->FACTURE_LOCAL_TAX2_OPTION) && $conf->global->FACTURE_LOCAL_TAX2_OPTION=='localtax2on')
				//{
				foreach ($this->localtax2 as $localtax_type => $localtax_rate)
				{
					if (in_array((string) $localtax_type, array('1', '3', '5'))) continue;

					foreach ($localtax_rate as $tvakey => $tvaval)
					{
						if ($tvakey != 0)    // On affiche pas taux 0
						{
							//$this->atleastoneratenotnull++;



							$index++;
							$pdf->SetXY($col1x, $tab2_top + $tab2_hl * $index);

							$tvacompl = '';
							if (preg_match('/\*/', $tvakey))
							{
								$tvakey = str_replace('*', '', $tvakey);
								$tvacompl = " (".$outputlangs->transnoentities("NonPercuRecuperable").")";
							}
							$totalvat = $outputlangs->transcountrynoentities("TotalLT2", $mysoc->country_code).' ';
							$totalvat .= vatrate(abs($tvakey), 1).$tvacompl;
							$pdf->MultiCell($col2x - $col1x, $tab2_hl, $totalvat, 0, 'L', 1);

							$pdf->SetXY($col2x, $tab2_top + $tab2_hl * $index);
							$pdf->MultiCell($largcol2, $tab2_hl, price($tvaval, 0, $outputlangs), 0, 'R', 1);
						}
					}
				}

                //}

				// VAT
				foreach ($this->tva as $tvakey => $tvaval)
				{
					if ($tvakey != 0)    // On affiche pas taux 0
					{
						$this->atleastoneratenotnull++;

						$index++;
						$pdf->SetXY($col1x+25, $tab2_top + $tab2_hl * $index);

						$tvacompl = '';
						if (preg_match('/\*/', $tvakey))
						{
							$tvakey = str_replace('*', '', $tvakey);
							$tvacompl = " (".$outputlangs->transnoentities("NonPercuRecuperable").")";
						}

						$totalvat = "Sales Tax";
						//$totalvat .= vatrate($tvakey, 1).$tvacompl;
						$pdf->MultiCell($col2x - $col1x, $tab2_hl, $totalvat, 0, 'L', 1);
$pdf->SetXY($col2x-50, $tab2_top + $tab2_hl * $index);
$pdf->MultiCell($col2x - $col1x, $tab2_hl+4, 'Sales tax 8.00%', 0, 'L', 0);

						$pdf->SetXY($col2x, $tab2_top + $tab2_hl * $index);
						$pdf->MultiCell($largcol2, $tab2_hl, price($tvaval, 0, $outputlangs), 0, 'R', 1);
					}
				}

				//Local tax 1 after VAT
				//if (! empty($conf->global->FACTURE_LOCAL_TAX1_OPTION) && $conf->global->FACTURE_LOCAL_TAX1_OPTION=='localtax1on')
				//{
				foreach ($this->localtax1 as $localtax_type => $localtax_rate) {
					if (in_array((string) $localtax_type, array('2', '4', '6'))) continue;

					foreach ($localtax_rate as $tvakey => $tvaval)
					{
						if ($tvakey != 0)    // On affiche pas taux 0
						{
							//$this->atleastoneratenotnull++;

							$index++;
							$pdf->SetXY($col1x, $tab2_top + $tab2_hl * $index);

							$tvacompl = '';
							if (preg_match('/\*/', $tvakey))
							{
								$tvakey = str_replace('*', '', $tvakey);
								$tvacompl = " (".$outputlangs->transnoentities("NonPercuRecuperable").")";
							}
							$totalvat = $outputlangs->transcountrynoentities("TotalLT1", $mysoc->country_code).' ';
							$totalvat .= vatrate(abs($tvakey), 1).$tvacompl;

							$pdf->MultiCell($col2x - $col1x, $tab2_hl, $totalvat, 0, 'L', 1);
							$pdf->SetXY($col2x, $tab2_top + $tab2_hl * $index);
							$pdf->MultiCell($largcol2, $tab2_hl, price($tvaval, 0, $outputlangs), 0, 'R', 1);
						}
					}
				}
	      		//}
				//Local tax 2 after VAT
				//if (! empty($conf->global->FACTURE_LOCAL_TAX2_OPTION) && $conf->global->FACTURE_LOCAL_TAX2_OPTION=='localtax2on')
				//{
				foreach ($this->localtax2 as $localtax_type => $localtax_rate)
				{
					if (in_array((string) $localtax_type, array('2', '4', '6'))) continue;

					foreach ($localtax_rate as $tvakey => $tvaval)
					{
						//$this->atleastoneratenotnull++;

						$index++;
						$pdf->SetXY($col1x, $tab2_top + $tab2_hl * $index);

						$tvacompl = '';
						if (preg_match('/\*/', $tvakey))
						{
							$tvakey = str_replace('*', '', $tvakey);
							$tvacompl = " (".$outputlangs->transnoentities("NonPercuRecuperable").")";
						}
						$totalvat = $outputlangs->transcountrynoentities("TotalLT2", $mysoc->country_code).' ';

						$totalvat .= vatrate(abs($tvakey), 1).$tvacompl;
						$pdf->MultiCell($col2x - $col1x, $tab2_hl, $totalvat, 0, 'L', 1);

						$pdf->SetXY($col2x, $tab2_top + $tab2_hl * $index);
						$pdf->MultiCell($largcol2, $tab2_hl, price($tvaval, 0, $outputlangs), 0, 'R', 1);
					}
				}
				//}

				// Revenue stamp
				if (price2num($object->revenuestamp) != 0)
				{
					$index++;
					$pdf->SetXY($col1x, $tab2_top + $tab2_hl * $index);
					$pdf->MultiCell($col2x - $col1x, $tab2_hl, $outputlangs->transnoentities("RevenueStamp"), $useborder, 'L', 1);

					$pdf->SetXY($col2x, $tab2_top + $tab2_hl * $index);
					$pdf->MultiCell($largcol2, $tab2_hl, price($sign * $object->revenuestamp), $useborder, 'R', 1);
				}

			
	
				// Retained warranty
				if (!empty($object->situation_final) && ($object->type == Facture::TYPE_SITUATION && (!empty($object->retained_warranty))))
				{
					$displayWarranty = false;

				    // Check if this situation invoice is 100% for real
				    if (!empty($object->lines)) {
				        $displayWarranty = true;
				        foreach ($object->lines as $i => $line) {
				            if ($line->product_type < 2 && $line->situation_percent < 100) {
				                $displayWarranty = false;
				                break;
				            }
						}
				    }

				    if ($displayWarranty) {
    				    $pdf->SetTextColor(40, 40, 40);
    				    $pdf->SetFillColor(255, 255, 255);

    				    $retainedWarranty = $object->total_ttc * $object->retained_warranty / 100;
    				    $billedWithRetainedWarranty = $object->total_ttc - $retainedWarranty;

    				    // Billed - retained warranty
    				    $index++;
    				    $pdf->SetXY($col1x, $tab2_top + $tab2_hl * $index);
    				    $pdf->MultiCell($col2x - $col1x, $tab2_hl, $outputlangs->transnoentities("ToPayOn", dol_print_date($object->date_lim_reglement, 'day')), $useborder, 'L', 1);

    				    $pdf->SetXY($col2x, $tab2_top + $tab2_hl * $index);
    				    $pdf->MultiCell($largcol2, $tab2_hl, price($billedWithRetainedWarranty), $useborder, 'R', 1);

    				    // retained warranty
    				    $index++;
    				    $pdf->SetXY($col1x, $tab2_top + $tab2_hl * $index);

    				    $retainedWarrantyToPayOn = $outputlangs->transnoentities("RetainedWarranty").' ('.$object->retained_warranty.'%)';
    				    $retainedWarrantyToPayOn .= !empty($object->retained_warranty_date_limit) ? ' '.$outputlangs->transnoentities("toPayOn", dol_print_date($object->retained_warranty_date_limit, 'day')) : '';

    				    $pdf->MultiCell($col2x - $col1x, $tab2_hl, $retainedWarrantyToPayOn, $useborder, 'L', 1);
    				    $pdf->SetXY($col2x, $tab2_top + $tab2_hl * $index);
    				    $pdf->MultiCell($largcol2, $tab2_hl, price($retainedWarranty), $useborder, 'R', 1);
						
				    }
				}
			}
		}

		$pdf->SetTextColor(0, 0, 0);
		$creditnoteamount = $object->getSumCreditNotesUsed(($conf->multicurrency->enabled && $object->multicurrency_tx != 1) ? 1 : 0); // Warning, this also include excess received
		$depositsamount = $object->getSumDepositsUsed(($conf->multicurrency->enabled && $object->multicurrency_tx != 1) ? 1 : 0);
		//print "x".$creditnoteamount."-".$depositsamount;exit;
		$resteapayer = price2num($total_ttc - $deja_regle - $creditnoteamount - $depositsamount, 'MT');
		if (!empty($object->paye)) $resteapayer = 0;

// Already paid + Deposits

			$index++;
			$pdf->SetXY($col1x+25, $tab2_top + $tab2_hl * $index);
                  $pdf->SetFont('', 'B', $default_font_size - 1);
			$pdf->MultiCell($col2x - $col1x, $tab2_hl, 'Please Pay This Amount', 0, 'L', 0);
			$pdf->SetXY($col2x, $tab2_top + $tab2_hl * $index);
$pdf->SetFont('', '', $default_font_size - 1);
			$pdf->MultiCell($largcol2, $tab2_hl, price($object->total_ttc, 0, $outputlangs), 0, 'R', 0);

		if (($deja_regle > 0 || $creditnoteamount > 0 || $depositsamount > 0) && empty($conf->global->INVOICE_NO_PAYMENT_DETAILS))
		{
			

			// Credit note
			if ($creditnoteamount)
			{
				$labeltouse = ($outputlangs->transnoentities("CreditNotesOrExcessReceived") != "CreditNotesOrExcessReceived") ? $outputlangs->transnoentities("CreditNotesOrExcessReceived") : $outputlangs->transnoentities("CreditNotes");
				$index++;
				$pdf->SetXY($col1x+25, $tab2_top + $tab2_hl * $index);
				$pdf->MultiCell($col2x - $col1x, $tab2_hl, $labeltouse, 0, 'L', 0);
				$pdf->SetXY($col2x, $tab2_top + $tab2_hl * $index);
				$pdf->MultiCell($largcol2, $tab2_hl, price(	 $creditnoteamount, 0, $outputlangs), 0, 'R', 0);
				//$pdf->MultiCell($largcol2, $tab2_hl, price(	 $multicurrency, 0, $outputlangs), 0, 'R', 0);
			}

			// Escompte
			if ($object->close_code == Facture::CLOSECODE_DISCOUNTVAT)
			{
				$index++;
				$pdf->SetFillColor(255, 255, 255);

				$pdf->SetXY($col1x+25, $tab2_top + $tab2_hl * $index);
				$pdf->MultiCell($col2x - $col1x, $tab2_hl, $outputlangs->transnoentities("EscompteOfferedShort"), $useborder, 'L', 1);
				$pdf->SetXY($col2x, $tab2_top + $tab2_hl * $index);
				$pdf->MultiCell($largcol2, $tab2_hl, price($object->total_ttc - $deja_regle - $creditnoteamount - $depositsamount - $multicurrency, 0, $outputlangs), $useborder, 'R', 1);

				$resteapayer = 0;
			}

			$index++;
			$pdf->SetTextColor(0, 0, 60);
			$pdf->SetFillColor(224, 224, 224);
			$pdf->SetXY($col1x+25, $tab2_top + $tab2_hl * $index);
			$pdf->MultiCell($col2x - $col1x, $tab2_hl, $outputlangs->transnoentities("RemainderToPay"), $useborder, 'L', 1);
			$pdf->SetXY($col2x, $tab2_top + $tab2_hl * $index);
			$pdf->MultiCell($largcol2, $tab2_hl, price($resteapayer, 0, $outputlangs), $useborder, 'R', 1);

			$pdf->SetFont('', '', $default_font_size - 1);
			$pdf->SetTextColor(0, 0, 0);
		}

		$index++;
		return ($tab2_top + ($tab2_hl * $index));
	}

	// phpcs:disable PEAR.NamingConventions.ValidFunctionName.PublicUnderscore
	/**
	 *   Show table for lines
	 *
	 *   @param		PDF			$pdf     		Object PDF
	 *   @param		string		$tab_top		Top position of table
	 *   @param		string		$tab_height		Height of table (rectangle)
	 *   @param		int			$nexY			Y (not used)
	 *   @param		Translate	$outputlangs	Langs object
	 *   @param		int			$hidetop		1=Hide top bar of array and title, 0=Hide nothing, -1=Hide only title
	 *   @param		int			$hidebottom		Hide bottom bar of array
	 *   @param		string		$currency		Currency code
	 *   @return	void
	 */
	protected function _tableau(&$pdf, $tab_top, $tab_height, $nexY, $outputlangs, $hidetop = 0, $hidebottom = 0, $currency = '')
	{
		global $conf;

		// Force to disable hidetop and hidebottom
		$hidebottom = 0;
		if ($hidetop) $hidetop = -1;

		$currency = !empty($currency) ? $currency : $conf->currency;
		$default_font_size = pdf_getPDFFontSize($outputlangs);

		// Amount in (at tab_top - 1)
		$pdf->SetTextColor(0, 0, 0);
		$pdf->SetFont('', '', $default_font_size - 2);

		if (empty($hidetop))
		{
			$titre = $outputlangs->transnoentities("AmountInCurrency", $outputlangs->transnoentitiesnoconv("Currency".$currency));
			$pdf->SetXY($this->page_largeur - $this->marge_droite - ($pdf->GetStringWidth($titre) + 3), $tab_top - 4);
		//	$pdf->MultiCell(($pdf->GetStringWidth($titre) + 3), 2, $titre);

			//$conf->global->MAIN_PDF_TITLE_BACKGROUND_COLOR='230,230,230';
			if (!empty($conf->global->MAIN_PDF_TITLE_BACKGROUND_COLOR)) $pdf->Rect($this->marge_gauche, $tab_top, $this->page_largeur - $this->marge_droite - $this->marge_gauche, 5, 'F', null, explode(',', $conf->global->MAIN_PDF_TITLE_BACKGROUND_COLOR));
		}

		$pdf->SetDrawColor(128, 128, 128);
		$pdf->SetFont('', '', $default_font_size - 1);

		// Output Rect
		$this->printRect($pdf, $this->marge_gauche, $tab_top, $this->page_largeur - $this->marge_gauche - $this->marge_droite, $tab_height, $hidetop, $hidebottom); // Rect takes a length in 3rd parameter and 4th parameter

		if (empty($hidetop))
		{
			$pdf->line($this->marge_gauche, $tab_top + 5, $this->page_largeur - $this->marge_droite, $tab_top + 5); // line takes a position y in 2nd parameter and 4th parameter

			$pdf->SetXY($this->posxdesc + 12 - 1, $tab_top + 1);
			$pdf->MultiCell(108, 2, $outputlangs->transnoentities("Designation"), '', 'L');
		}

		if (!empty($conf->global->MAIN_GENERATE_INVOICES_WITH_PICTURE))
		{
			$pdf->line($this->posxpicture - 1, $tab_top, $this->posxpicture - 1, $tab_top + $tab_height);
			if (empty($hidetop))
			{
				//$pdf->SetXY($this->posxpicture-1, $tab_top+1);
				//$pdf->MultiCell($this->posxtva-$this->posxpicture-1,2, $outputlangs->transnoentities("Photo"),'','C');
			}
		}

		if (empty($conf->global->MAIN_GENERATE_DOCUMENTS_WITHOUT_VAT) && empty($conf->global->MAIN_GENERATE_DOCUMENTS_WITHOUT_VAT_COLUMN))
		{
			$pdf->line($this->posxtva - 1, $tab_top, $this->posxtva - 1, $tab_top + $tab_height);
			if (empty($hidetop))
			{
				$pdf->SetXY($this->posxtva - 3, $tab_top + 1);
				$pdf->MultiCell($this->posxup - $this->posxtva + 3, 2, ("T"), '', 'C');
			}
		}

		$pdf->line($this->posxup - 1, $tab_top, $this->posxup - 1, $tab_top + $tab_height);
		if (empty($hidetop))
		{
			$pdf->SetXY($this->posxup - 1, $tab_top + 1);
			$pdf->MultiCell($this->posxqty - $this->posxup - 1, 2, $outputlangs->transnoentities("PriceUHT"), '', 'C');
		}

		$pdf->line($this->posxqty - 1, $tab_top, $this->posxqty - 1, $tab_top + $tab_height);
		if (empty($hidetop))
		{
			$pdf->SetXY($this->posxqty - 1, $tab_top + 1);
			$pdf->MultiCell($this->posxunit - $this->posxqty - 1, 2,$outputlangs->transnoentities("Qty"), '', 'C');
		}

		if (!empty($conf->global->PRODUCT_USE_UNITS))
		{
			$pdf->line($this->posxunit - 1, $tab_top, $this->posxunit - 1, $tab_top + $tab_height);
			if (empty($hidetop)) {
				$pdf->SetXY($this->posxunit - 1, $tab_top + 1);
				$pdf->MultiCell($this->posxdiscount - $this->posxunit - 1, 2, "U/M", '', 'C');
			}
		}

		if ($this->atleastonediscount)
		{
		    $pdf->line($this->posxdiscount - 1, $tab_top, $this->posxdiscount - 1, $tab_top + $tab_height);
    		if (empty($hidetop))
    		{
   		   $pdf->SetXY($this->posxdiscount - 1, $tab_top + 1);
   			$pdf->MultiCell($this->posxprogress - $this->posxdiscount + 1, 2, $outputlangs->transnoentities("ReductionShort"), '', 'C');
    		}
        }

        if ($this->situationinvoice) {
            $pdf->line($this->posxprogress - 1, $tab_top, $this->posxprogress - 1, $tab_top + $tab_height);
            if (empty($hidetop)) {
                $pdf->SetXY($this->posxprogress, $tab_top + 1);
                $pdf->MultiCell($this->postotalht - $this->posxprogress, 2, $outputlangs->transnoentities("ProgressShort"), '', 'C');
            }
        }

        $pdf->line($this->postotalht, $tab_top, $this->postotalht, $tab_top + $tab_height);
		if (empty($hidetop))
		{
			$pdf->SetXY($this->postotalht - 1, $tab_top + 1);
			$pdf->MultiCell(30, 2, $outputlangs->transnoentities("TotalHT"), '', 'C');
    }
    
    // Accountancy piece
		$pdf->line($this->posxdesc + 10, $tab_top, $this->posxdesc + 10, $tab_top + $tab_height);
		if (empty($hidetop))
		{
			$pdf->SetXY($this->posxdesc-5 , $tab_top + 1);
			$pdf->MultiCell($this->posxqty - $this->posxup - 1, 2, "Line", '', 'C');
		}
	}

	// phpcs:disable PEAR.NamingConventions.ValidFunctionName.PublicUnderscore
	/**
	 *  Show top header of page.
	 *
	 *  @param	PDF			$pdf     		Object PDF
	 *  @param  Object		$object     	Object to show
	 *  @param  int	    	$showaddress    0=no, 1=yes
	 *  @param  Translate	$outputlangs	Object lang for output
	 *  @return	void
	 */
	protected function _pagehead(&$pdf, $object, $showaddress, $outputlangs)
	{
		global $conf, $langs;

		// Load traductions files required by page
		$outputlangs->loadLangs(array("main", "bills", "propal", "companies"));

		$default_font_size = pdf_getPDFFontSize($outputlangs);

		pdf_pagehead($pdf, $outputlangs, $this->page_hauteur);

		// Show Draft Watermark
		if ($object->statut == Facture::STATUS_DRAFT && (!empty($conf->global->FACTURE_DRAFT_WATERMARK)))
        {
		      pdf_watermark($pdf, $outputlangs, $this->page_hauteur, $this->page_largeur, 'mm', $conf->global->FACTURE_DRAFT_WATERMARK);
        }

		$pdf->SetTextColor(0, 0, 60);
		$pdf->SetFont('', 'B', $default_font_size + 3);

		$w = 110;

		$posy = $this->marge_haute;
        $posx = $this->page_largeur - $this->marge_droite - $w;

		$pdf->SetXY($this->marge_gauche, $posy);

		// Logo
		if (empty($conf->global->PDF_DISABLE_MYCOMPANY_LOGO))
		{
			if ($this->emetteur->logo)
			{
				$logodir = $conf->mycompany->dir_output;
				if (!empty($conf->mycompany->multidir_output[$object->entity])) $logodir = $conf->mycompany->multidir_output[$object->entity];
				if (empty($conf->global->MAIN_PDF_USE_LARGE_LOGO))
				{
					$logo = $logodir.'/logos/thumbs/'.$this->emetteur->logo_small;
				} else {
					$logo = $logodir.'/logos/'.$this->emetteur->logo;
				}
				if (is_readable($logo))
				{
					$height = pdf_getHeightForLogo($logo);
					$pdf->Image($logo, $this->marge_gauche, $posy, 0, $height); // width=0 (auto)
				} else {
					$pdf->SetTextColor(200, 0, 0);
					$pdf->SetFont('', 'B', $default_font_size - 2);
					$pdf->MultiCell($w, 3, $outputlangs->transnoentities("ErrorLogoFileNotFound", $logo), 0, 'L');
					$pdf->MultiCell($w, 3, $outputlangs->transnoentities("ErrorGoToGlobalSetup"), 0, 'L');
				}
			} else {
				$text = $this->emetteur->name;
				$pdf->MultiCell($w, 4, $outputlangs->convToOutputCharset($text), 0, 'L');
			}
		}


		$pdf->SetFont('', 'B', $default_font_size + 3);
		$pdf->SetXY($posx, $posy);
		$pdf->SetTextColor(0, 0, 60);
		$title = $outputlangs->transnoentities("PdfInvoiceTitle");
		if ($object->type == 1) $title = $outputlangs->transnoentities("InvoiceReplacement");
		if ($object->type == 2) $title = $outputlangs->transnoentities("InvoiceAvoir");
		if ($object->type == 3) $title = $outputlangs->transnoentities("InvoiceDeposit");
		if ($object->type == 4) $title = $outputlangs->transnoentities("InvoiceProForma");
		if ($this->situationinvoice) $title = $outputlangs->transnoentities("InvoiceSituation");
		

		$pdf->MultiCell($w, 3, $title, '', 'R');

 $sql9=  "SELECT f.fk_soc AS aut  FROM  ".MAIN_DB_PREFIX."facture AS f ";
        $sql9.= " LEFT JOIN ".MAIN_DB_PREFIX."facture_extrafields  AS fax ON f.rowid = fax.fk_object ";
        $sql9.= " WHERE   f.rowid =  " . $object->id . " GROUP BY f.rowid";
        $resql9 = $this->db->query($sql9);
        foreach($resql9 as $value1){

            $fax = $value1['aut'] ;
            

        }

// QRCODE,L : QR-CODE Low error correction
$pdf->write2DBarcode('http://192.168.15.36/comm/card.php?socid='.$fax.'&save_lastsearch_values=1', 'QRCODE,L', 112, 27, 26, 28, $style, 'N');
//$pdf->write2DBarcode($res, 'QRCODE,L', 112, 27, 26, 28, $style, 'N');
//$pdf->Text(20, 25, $fax. '  rey  ');

		$pdf->SetFont('', 'B', $default_font_size);

		$posy += 5;
		$pdf->SetXY($posx, $posy);
		$pdf->SetTextColor(0, 0, 60);
		$textref = $outputlangs->transnoentities("Ref")." : ".$outputlangs->convToOutputCharset($object->ref);
		if ($object->statut == Facture::STATUS_DRAFT)
		{
			$pdf->SetTextColor(128, 0, 0);
			$textref .= ' - '.$outputlangs->transnoentities("NotValidated");
		}
		

		$pdf->MultiCell($w, 4, $textref, '', 'R');

		$posy += 1;
		$pdf->SetFont('', '', $default_font_size - 2);

		if ($object->ref_client)
		{
			$posy += 4;
			$pdf->SetXY($posx, $posy);
			$pdf->SetTextColor(0, 0, 60);
			$pdf->MultiCell($w, 3, $outputlangs->transnoentities("RefCustomer")." : ".$outputlangs->convToOutputCharset($object->ref_client), '', 'R');
		}

$posy = pdf_writeLinkedObjects($pdf, $object, $outputlangs, $posx, $posy, $w, 3, 'R', $default_font_size);
									// Box Customer invoice
			$pdf->SetTextColor(0, 0, 0);
			$pdf->SetFont('', '', $default_font_size - 2);
			$pdf->SetXY($posx +50, $posy+2.5);
			$pdf->SetFillColor(0, 0, 0);
			$pdf->MultiCell( $widthrecbox, 8, "", 0, 'C', 1);
			$pdf->SetTextColor(255, 255, 255);
//$pdf->MultiCell($w, +13, "Customer Invoice", '', 'R');

			
			//$pdf->MultiCell($widthrecbox, "CUSTOMER INVOICE", 0, 'R');
					//$pdf->SetTextColor(255, 255, 255);
		//$pdf->MultiCell($w, +13, $outputlangs->transnoentities("CUSTOMER INVOICE"), '', 'R');
			

		$objectidnext = $object->getIdReplacingInvoice('validated');
		if ($object->type == 0 && $objectidnext)
		{
			$objectreplacing = new Facture($this->db);
			$objectreplacing->fetch($objectidnext);

			$posy += 3;
			$pdf->SetXY($posx, $posy);
			$pdf->SetTextColor(0, 0, 60);
			//$pdf->MultiCell($w, 3, $outputlangs->transnoentities("ReplacementByInvoice").' : '.$outputlangs->convToOutputCharset($objectreplacing->ref), '', 'R');
		}
		if ($object->type == 1)
		{
			$objectreplaced = new Facture($this->db);
			$objectreplaced->fetch($object->fk_facture_source);

			$posy += 4;
			$pdf->SetXY($posx, $posy);
			$pdf->SetTextColor(0, 0, 60);
		//	$pdf->MultiCell($w, 3, $outputlangs->transnoentities("ReplacementInvoice").' : '.$outputlangs->convToOutputCharset($objectreplaced->ref), '', 'R');
		}
		if ($object->type == 2 && !empty($object->fk_facture_source))
		{
			$objectreplaced = new Facture($this->db);
			$objectreplaced->fetch($object->fk_facture_source);

			$posy += 3;
			$pdf->SetXY($posx, $posy);
			$pdf->SetTextColor(0, 0, 60);
			//$pdf->MultiCell($w, 3, $outputlangs->transnoentities("CorrectionInvoice").' : '.$outputlangs->convToOutputCharset($objectreplaced->ref), '', 'R');
		}

		$posy += 8;
		$pdf->SetXY($posx, $posy-5);
		$pdf->SetFont('', 'B', $default_font_size + 6);
		$pdf->SetTextColor(255, 255, 255);
		$pdf->MultiCell($w, +13, $outputlangs->transnoentities("CUSTOMER INVOICE"), '', 'R');

		if (!empty($conf->global->INVOICE_POINTOFTAX_DATE))
		{
			$posy += 4;
			$pdf->SetXY($posx, $posy);
			$pdf->SetTextColor(0, 0, 60);
			//$pdf->MultiCell($w, 3, $outputlangs->transnoentities("DatePointOfTax")." : ".dol_print_date($object->date_pointoftax, "day", false, $outputlangs), '', 'R');
		}

		if ($object->type != 2)
		{
			$posy += 3;
			$pdf->SetXY($posx, $posy);
			$pdf->SetTextColor(0, 0, 60);
			//$pdf->MultiCell($w, 3, $outputlangs->transnoentities("DateDue")." : ".dol_print_date($object->date_lim_reglement, "day", false, $outputlangs, true), '', 'R');
		}

		if ($object->thirdparty->code_client)
		{
			$posy += 3;
			$pdf->SetXY($posx, $posy);
			
			$pdf->SetTextColor(0, 0, 60);
			//$pdf->MultiCell($w, 3, $outputlangs->transnoentities("CustomerCode")." : ".$outputlangs->transnoentities($object->thirdparty->code_client), '', 'R');
		}
		



		// Get contact
		if (!empty($conf->global->DOC_SHOW_FIRST_SALES_REP))
		{
		    $arrayidcontact = $object->getIdContact('internal', 'SALESREPFOLL');
		    if (count($arrayidcontact) > 0)
		    {
		        $usertmp = new User($this->db);
		        $usertmp->fetch($arrayidcontact[0]);
                $posy += 4;
                $pdf->SetXY($posx, $posy);
		        $pdf->SetTextColor(0, 0, 60);
		        $pdf->MultiCell($w, 3, $langs->transnoentities("SalesRepresentative")." : ".$usertmp->getFullName($langs), '', 'R');
		    }

		}

		$posy += 1;

		$top_shift = 0;
		
		// Show list of linked objects
		$current_y = $pdf->getY();
		//$posy = pdf_writeLinkedObjects($pdf, $object, $outputlangs, $posx, $posy, $w, 3, 'R', $default_font_size);
		if ($current_y < $pdf->getY())
		{
			$top_shift = $pdf->getY() - $current_y;
		}

		if ($showaddress)
		{
			// Sender properties
			$carac_emetteur = pdf_build_address($outputlangs, $this->emetteur, $object->thirdparty, '', 0, 'source', $object);
			if ($usecontact && !empty($conf->global->MAIN_USE_COMPANY_NAME_OF_CONTACT)) {
				$thirdparty = $object->contact;
			} else {
				$thirdparty = $object->thirdparty;
			}

			$carac_client_name = pdfBuildThirdpartyName($thirdparty, $outputlangs);

			$carac_client = pdf_build_address($outputlangs, $this->emetteur, $object->thirdparty, ($usecontact ? $object->contact : ''), $usecontact, 'target', $object);
			
								// Show sender name
	$pdf->SetXY($posx - 21, $posy - 14);
			$pdf->SetFont('', 'B', $default_font_size);
			$pdf->MultiCell($widthrecbox, 5, "", 0, 'L');
						//$pdf->MultiCell($widthrecbox, 5, "Buy and Sell", 0, 'L');
			$pdf->MultiCell($widthrecbox, 5, "2830 Simpson Cir
Norcross, Georgia, 30071
", 0, 'L');

			// Show sender
			$posy = !empty($conf->global->MAIN_PDF_USE_ISO_LOCATION) ? 40 : 57;
			$posy += $top_shift;
			$posx = $this->marge_gauche;
			if (!empty($conf->global->MAIN_INVERT_SENDER_RECIPIENT)) $posx = $this->page_largeur - $this->marge_droite - 80;

			$hautcadre = !empty($conf->global->MAIN_PDF_USE_ISO_LOCATION) ? 38 : 25;
			$widthrecbox = !empty($conf->global->MAIN_PDF_USE_ISO_LOCATION) ? 92 : 62;


			// Show sender frame
			$pdf->SetTextColor(0, 0, 0);
			$pdf->SetFont('', '', $default_font_size - 2);
			$pdf->SetXY($posx, $posy - 5);
			$pdf->MultiCell(66, 5, "SOLD TO".":", 0, 'L');
			$pdf->SetXY($posx, $posy);
			$pdf->SetFillColor(230, 230, 230);
			$pdf->MultiCell($widthrecbox, $hautcadre, "", 0, 'R', 1);
			$pdf->SetTextColor(0, 0, 60);

			// Show sender name
			$pdf->SetXY($posx + 2, $posy + 3);
			$pdf->SetFont('', 'B', $default_font_size);
			//$pdf->MultiCell($widthrecbox - 2, 4, $outputlangs->convToOutputCharset($this->emetteur->name), 0, 'L');
			$pdf->MultiCell($widthrecbox, 2, $carac_client_name, 0, 'L');
			$posy = $pdf->getY();

			// Show sender information
			$pdf->SetXY($posx + 2, $posy);
			$pdf->SetFont('', '', $default_font_size - 1);
			//$pdf->MultiCell($widthrecbox - 2, 4, $carac_emetteur, 0, 'L');
			$pdf->MultiCell($widthrecbox, 4, $carac_client, 0, 'L');



			// If BILLING contact defined on invoice, we use it
			$usecontact = false;
			$arrayidcontact = $object->getIdContact('external', 'SHIPPING');
			if (count($arrayidcontact) > 0)
			{
				$usecontact = true;
				$result = $object->fetch_contact($arrayidcontact[0]);
			}

			//Recipient name
			// On peut utiliser le nom de la societe du contact
			if ($usecontact && !empty($conf->global->MAIN_USE_COMPANY_NAME_OF_CONTACT)) {
				$thirdparty = $object->contact;
			} else {
				$thirdparty = $object->thirdparty;
			}

			$carac_client_name = pdfBuildThirdpartyName($thirdparty, $outputlangs);

			$carac_client = pdf_build_address($outputlangs, $this->emetteur, $object->thirdparty, ($usecontact ? $object->contact : ''), $usecontact, 'target', $object);

			// Show recipient
			$widthrecbox = !empty($conf->global->MAIN_PDF_USE_ISO_LOCATION) ? 92 : 62;
			if ($this->page_largeur < 210) $widthrecbox = 84; // To work with US executive format
			$posy = !empty($conf->global->MAIN_PDF_USE_ISO_LOCATION) ? 40 : 57;
			$posy += $top_shift;
			$posx = $this->page_largeur - $this->marge_droite - $widthrecbox -68;
			if (!empty($conf->global->MAIN_INVERT_SENDER_RECIPIENT)) $posx = $this->marge_gauche;

			// Show recipient frame
			$pdf->SetTextColor(0, 0, 0);
			$pdf->SetFont('', '', $default_font_size - 2);
			$pdf->SetXY($posx + 2, $posy - 5);
			$pdf->MultiCell($widthrecbox, 5, "JOB ADDRESS".":", 0, 'L');
			$pdf->Rect($posx, $posy, $widthrecbox, $hautcadre);

			// Show recipient name
			$pdf->SetXY($posx+2, $posy+3);
			$pdf->SetFont('', 'B', $default_font_size);
			//$pdf->MultiCell($widthrecbox, 4, $carac_client_name, 0, 'L');
			//$arrayidcontact=$object->getIdContact('External', 'SHIPPING');

			$posy = $pdf->getY();

			// Show recipient information
			$pdf->SetFont('', 'B', $default_font_size);
			$pdf->SetXY($posx+2, $posy);
			//$pdf->MultiCell($widthrecbox, 4, $carac_client, 0, 'L');
			$pdf->SetXY($posx+2, $posy+3);
			
			$arrayidcontact=$object->getIdContact('External','SHIPPING');
			$posy = 5;
			$posx= -7;
	        $usecontact=true;	
			$result = $object->fetch_contact($arrayidcontact[0]);
			

		$posy += 1;

		$top_shift = 0;

			$posy = $pdf->getY();


			
			//$pdf->MultiCell($widthrecbox, 4, $carac_client, 0, 'L');
			// CODIGO DE BARRAS 
			//$pdf->write2DBarcode($code, $encoding, $x, $y, $w, $h, $this->_style2d, $this->_align2d);
			//$pdf->Cell(0, 0, 'CODE 128 AUTO', 0, 1);
		//	$pdf->SetXY($posx + 4, $posy + 9);
//$pdf->write1DBarcode('CODE 128 AUTO', 'C128', '', '', '', 16, 0.4, $style, 'N');

//$pdf->Ln();
	//		$pdf->pdf_tcpdflabel;
		//	print $object->barcode;
		
					// Show recipient
			$widthrecbox = !empty($conf->global->MAIN_PDF_USE_ISO_LOCATION) ? 92 : 62;
			if ($this->page_largeur < 210) $widthrecbox = 84; // To work with US executive format
			$posy = !empty($conf->global->MAIN_PDF_USE_ISO_LOCATION) ? 40 : 57;
			$posy += $top_shift;
			$posx = $this->page_largeur - $this->marge_droite - $widthrecbox -1;
			if (!empty($conf->global->MAIN_INVERT_SENDER_RECIPIENT)) $posx = $this->marge_gauche;

			// Show recipient frame
			$pdf->SetTextColor(0, 0, 0);
			$pdf->SetFont('', '', $default_font_size - 2);
			$pdf->SetXY($posx + 2, $posy - 5);
			//$pdf->MultiCell($widthrecbox, 5, $outputlangs->transnoentities("BillTo").":", 0, 'L');
			$pdf->Rect($posx, $posy, $widthrecbox, $hautcadre);
			 

 $sql =" SELECT CASE(fex.customerpickup) 
WHEN '0' THEN 'No'
WHEN '1' THEN 'Yes'
ELSE ''
END AS cup,
CONCAT(u.firstname,' ',u.lastname) AS executive
FROM  llx_facture_extrafields AS fex
LEFT JOIN llx_user AS u ON u.rowid = fex.salesexecutive
WHERE fex.fk_object = " . $object->id;
 $resql3 = $this->db->query($sql);
				foreach($resql3 as $value3) {


					$cup = $value3['cup'];
                              $exe = $value3['executive'];

                             

				}
			 		
			$pdf->SetXY($posx + 2, $posy + 1);
			$pdf->SetFont('', '', 9);
			$pdf->MultiCell($w, 3, $outputlangs->transnoentities("DateInvoice")." : ".dol_print_date($object->date, "day", false, $outputlangs), 0, 'L');
			$pdf->SetXY($posx + 2, $posy + 5);
			$pdf->SetFont('', '', 9);
			$pdf->MultiCell($w, 3, $outputlangs->transnoentities("DateDue")." : ".dol_print_date($object->date_lim_reglement, "day", false, $outputlangs, true), 0, 'L');
			$pdf->SetXY($posx + 2, $posy + 8.5);
			$pdf->SetFont('', '', 9);
			$pdf->MultiCell($w, 3, $outputlangs->transnoentities("CustomerCode")." : ".$outputlangs->transnoentities($object->thirdparty->code_client), 0, 'L');
			   $pdf->SetXY($posx + 2, $posy + 20);
			$pdf->SetFont('', '', 9);
			$pdf->MultiCell($w, 3, "Cust Pick Up"." : " . $cup, 0, 'L');
						   $pdf->SetXY($posx + 2, $posy + 16);
			$pdf->SetFont('', '', 9);
			$pdf->MultiCell($w, 3, "Sales Rep."." : ". $exe, 0, 'L');
			//$posy = pdf_writeLinkedObjects($pdf, $object, $outputlangs, $posx+2, $posy+5, $w-60, 33, 'L', 10.5);





			}

		$pdf->SetTextColor(0, 0, 0);
		return $top_shift;
	}

	// phpcs:disable PEAR.NamingConventions.ValidFunctionName.PublicUnderscore
	/**
	 *   	Show footer of page. Need this->emetteur object
     *
	 *   	@param	PDF			$pdf     			PDF
	 * 		@param	Object		$object				Object to show
	 *      @param	Translate	$outputlangs		Object lang for output
	 *      @param	int			$hidefreetext		1=Hide free text
	 *      @return	int								Return height of bottom margin including footer text
	 */
	protected function _pagefoot(&$pdf, $object, $outputlangs, $hidefreetext = 0)
	{

		global $conf;
		$showdetails = $conf->global->MAIN_GENERATE_DOCUMENTS_SHOW_FOOT_DETAILS;
		return pdf_pagefoot($pdf, $outputlangs, 'INVOICE_FREE_TEXT', $this->emetteur, $this->marge_basse, $this->marge_gauche, $this->page_hauteur, $object, $showdetails, $hidefreetext);
	}
}
