<?php

/**
 * Femto Framework
 *
 * @author James White <dev.jameswhite@gmail.com>
 * @license http://opensource.org/licenses/MIT MIT
 */

class FemtoException extends Exception {}
class FemtoPageNotFoundException extends FemtoException {}
class FemtoFragmentNotFoundException extends FemtoException {}
class FemtoConfigNotFoundException extends FemtoException {}
